<?php

namespace Database\Seeders;

use App\Models\KnowledgeSource;
use Illuminate\Database\Seeder;

/**
 * Registers the external knowledge sources used by knowledge:fetch. Reference metadata only, no medical content.
 *
 * The licence text below summarises the official pages listed in docs/sources.md. license_status is ALWAYS created as
 * "unverified": a human must read the licence and confirm it in the admin before anything is downloaded.
 * Existing rows are never modified (a confirmation already given is kept).
 */
class KnowledgeSourceSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            'icd11' => [
                'name' => 'ICD-11 (CIM-11), Mortality and Morbidity Statistics',
                'publisher' => 'World Health Organization (WHO)',
                'base_url' => 'https://icd.who.int',
                'license' => 'CC BY-ND 3.0 IGO + ICD-11 Terms of Use',
                'license_url' => 'https://icd.who.int/en/docs/icd11-license.pdf',
                'allowed_use' => implode("\n", [
                    'Licence page: https://icd.who.int/docs/icd-api/license/ (CC BY-ND 3.0 IGO).',
                    'Storage and display must keep the ICD-11 code, title and URI.',
                    'Producing translations or mappings is NOT covered by the licence (separate written agreement with WHO): only languages published by WHO may be used.',
                    'Data added by us must be clearly identified as not originating from WHO.',
                    'Commercial-use clauses and the terms accepted when creating API credentials: to be read and confirmed.',
                ]),
                'attribution' => 'International Classification of Diseases, Eleventh Revision (ICD-11), World Health Organization (WHO) 2019',
            ],
            'medlineplus' => [
                'name' => 'MedlinePlus Health Topics (web service)',
                'publisher' => 'U.S. National Library of Medicine (NLM)',
                'base_url' => 'https://medlineplus.gov',
                'license' => 'Public domain for health topic summaries (U.S. federal work); A.D.A.M. and ASHP content excluded',
                'license_url' => 'https://medlineplus.gov/about/using/usingcontent/',
                'allowed_use' => implode("\n", [
                    'Web service: https://medlineplus.gov/about/developers/webservices/ (free, no registration, max 85 requests/minute/IP).',
                    'Summaries on health topic pages are listed as public domain. A.D.A.M. Medical Encyclopedia and ASHP drug monographs are copyrighted: never reuse them.',
                    'Do not use the MedlinePlus logo or imply endorsement.',
                    'English and Spanish only: no French or Arabic text is provided.',
                    'To confirm: the reuse page does not say explicitly that the summaries returned by the web service / XML files are the same public-domain summaries.',
                ]),
                'attribution' => 'Source: MedlinePlus, National Library of Medicine',
            ],
        ];

        foreach ($sources as $code => $attributes) {
            KnowledgeSource::firstOrCreate(['code' => $code], $attributes + ['license_status' => 'unverified', 'is_active' => true]);
        }
    }
}
