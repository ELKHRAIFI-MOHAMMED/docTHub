<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Resources\AdminRecordResource;
use App\Models\Doctor;
use App\Models\DoctorDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Doctor profiles, photos, verification documents and verification decisions (admin only). */
class DoctorController extends AdminCrudController
{
    protected array $with = ['specialties:id,code,name', 'languages:id,code,name', 'locations', 'documents'];
    protected array $searchable = ['first_name', 'last_name', 'email'];
    protected array $filterable = ['verification_status', 'is_demo'];
    protected string $defaultSort = 'last_name';

    protected function modelClass(): string
    {
        return Doctor::class;
    }

    protected function rules(Request $request, ?Model $model): array
    {
        return [
            'title' => ['nullable', 'string', 'max:20'],
            'first_name' => [$this->req($model), 'string', 'max:100'],
            'last_name' => [$this->req($model), 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:10000'],
            'qualifications' => ['nullable', 'string', 'max:10000'],
            'years_of_experience' => ['nullable', 'integer', 'between:0,70'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9 ()\-.]{6,32}$/'],
            'website' => ['nullable', 'url', 'max:255'],
            'consultation_fee' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'fee_currency' => ['nullable', 'string', 'size:3', 'alpha', 'required_with:consultation_fee'],
            'is_demo' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(Doctor::STATUSES)],
            'specialties' => [$this->req($model), 'array', 'min:1'],
            'specialties.*.id' => ['required', 'integer', 'exists:specialties,id'],
            'specialties.*.is_primary' => ['sometimes', 'boolean'],
            'language_ids' => ['sometimes', 'array'],
            'language_ids.*' => ['integer', 'exists:languages,id'],
            'locations' => [$this->req($model), 'array', 'min:1', 'max:10'],
            'locations.*.label' => ['nullable', 'string', 'max:150'],
            'locations.*.address' => ['nullable', 'string', 'max:255'],
            'locations.*.country_id' => ['required', 'integer', 'exists:countries,id'],
            'locations.*.city_id' => ['required', 'integer', 'exists:cities,id'],
            'locations.*.postal_code' => ['nullable', 'string', 'max:20'],
            'locations.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'locations.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'locations.*.is_primary' => ['sometimes', 'boolean'],
        ];
    }

    protected function afterSave(Model $model, array $data, Request $request): void
    {
        /** @var Doctor $model */
        if (array_key_exists('specialties', $data)) {
            $model->specialties()->sync(collect($data['specialties'])->mapWithKeys(fn ($s, $i) => [
                $s['id'] => ['is_primary' => (bool) ($s['is_primary'] ?? $i === 0)],
            ])->all());
        }
        if (array_key_exists('language_ids', $data)) {
            $model->languages()->sync($data['language_ids']);
        }
        if (array_key_exists('locations', $data)) {
            $model->locations()->delete();
            foreach (array_values($data['locations']) as $i => $loc) {
                // City must belong to the given country.
                if (! \App\Models\City::where('id', $loc['city_id'])->where('country_id', $loc['country_id'])->exists()) {
                    abort(422, "Location #".($i + 1).": the city does not belong to the selected country.");
                }
                $model->locations()->create($loc + ['is_primary' => $loc['is_primary'] ?? $i === 0]);
            }
        }
    }

    /** Verification decision. Verifying a real (non-demo) doctor requires an approved document. */
    public function verify(Request $request, int $id): AdminRecordResource
    {
        $data = $request->validate([
            'verification_status' => ['required', Rule::in(Doctor::VERIFICATION_STATUSES)],
        ]);
        $doctor = Doctor::findOrFail($id);

        if ($data['verification_status'] === 'verified' && ! $doctor->is_demo
            && ! $doctor->documents()->where('status', 'approved')->exists()) {
            abort(422, 'Approve at least one verification document before verifying this doctor.');
        }

        $old = $doctor->verification_status;
        $doctor->forceFill([
            'verification_status' => $data['verification_status'],
            'verified_at' => $data['verification_status'] === 'verified' ? now() : null,
            'verified_by' => $data['verification_status'] === 'verified' ? $request->user()->id : null,
        ])->save();

        $this->audit->log('admin.doctor_verification', $doctor, ['verification_status' => $old], ['verification_status' => $data['verification_status']]);

        return new AdminRecordResource($doctor->fresh($this->with));
    }

    public function uploadPhoto(Request $request, int $id): AdminRecordResource
    {
        $request->validate(['photo' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:2048', 'dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000']]);
        $doctor = Doctor::findOrFail($id);
        $disk = Storage::disk(config('docthub.public_disk'));

        if ($doctor->photo_path) {
            $disk->delete($doctor->photo_path);
        }
        // Stored under a random name; the original file name is never used in the path.
        $path = $request->file('photo')->store('doctors/photos', config('docthub.public_disk'));
        $doctor->forceFill(['photo_path' => $path])->save();

        $this->audit->log('admin.doctor_photo_uploaded', $doctor);

        return new AdminRecordResource($doctor->fresh($this->with));
    }

    public function uploadDocument(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(DoctorDocument::TYPES)],
            'file' => ['required', 'file', 'mimes:pdf,jpeg,png', 'max:5120'],
        ]);
        $doctor = Doctor::findOrFail($id);
        $file = $request->file('file');
        $disk = config('docthub.private_disk');

        $document = $doctor->documents()->create([
            'type' => $data['type'],
            'disk' => $disk,
            'path' => $file->store("doctors/{$doctor->id}/documents", $disk),
            'original_name' => substr(basename($file->getClientOriginalName()), 0, 255),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        $this->audit->log('admin.doctor_document_uploaded', $doctor, null, ['document_id' => $document->id, 'type' => $document->type]);

        return response()->json(['data' => $document], 201);
    }

    public function reviewDocument(Request $request, int $id, int $document): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'approved', 'rejected'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $doc = DoctorDocument::where('doctor_id', $id)->findOrFail($document);
        $doc->forceFill($data + ['reviewed_by' => $request->user()->id, 'reviewed_at' => now()])->save();

        $this->audit->log('admin.doctor_document_reviewed', $doc->doctor, null, ['document_id' => $doc->id, 'status' => $doc->status]);

        return response()->json(['data' => $doc]);
    }

    public function downloadDocument(int $id, int $document): StreamedResponse
    {
        $doc = DoctorDocument::where('doctor_id', $id)->findOrFail($document);
        $this->audit->log('admin.doctor_document_downloaded', $doc->doctor, null, ['document_id' => $doc->id]);

        return Storage::disk($doc->disk)->download($doc->path, $doc->original_name);
    }
}
