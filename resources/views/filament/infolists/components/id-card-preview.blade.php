@php
    $record = (isset($getRecord) && is_callable($getRecord) ? $getRecord() : null) ?? ($record ?? null);
    $metadata = $record?->metadata ?? [];
    $meta = is_array($metadata) ? $metadata : (json_decode($metadata, true) ?? []);
    $meta = is_array($meta) ? $meta : [];

    $firstName = $meta['id_first_name'] ?? $meta['first_name'] ?? '';
    $middleName = $meta['id_middle_name'] ?? $meta['middle_name'] ?? '';
    $middleInitial = filled($middleName) ? mb_strtoupper(mb_substr(trim($middleName), 0, 1)).'.' : '';
    $lastName = $meta['id_last_name'] ?? $meta['last_name'] ?? '';
    $suffix = $meta['id_suffix'] ?? $meta['suffix'] ?? '';
    $fullName = trim(implode(' ', array_filter([$firstName, $middleInitial, $lastName, $suffix])));
    $fullName = $fullName !== '' ? $fullName : 'EMPLOYEE NAME';

    $position = strtoupper($meta['id_position'] ?? $meta['position'] ?? 'POSITION / TITLE');
    $idNumber = filled($meta['id_number'] ?? null)
        ? $meta['id_number']
        : (filled($meta['official_id_number'] ?? null) ? $meta['official_id_number'] : '2026-XXXXXXX');

    $storageUrl = static function ($path): ?string {
        if (! is_string($path) || blank($path)) {
            return null;
        }

        $cleanPath = preg_replace('#^(?:(?:public|storage)/)+#', '', ltrim($path, '/'));

        return asset('storage/'.$cleanPath);
    };
    $photoPath = $meta['id_photo_path'] ?? $meta['id_photo'] ?? $meta['photo'] ?? null;
    $photoUrl = $storageUrl($photoPath);
    $signaturePath = $meta['client_signature_path'] ?? $meta['id_signature'] ?? $meta['signature'] ?? null;
    $signatureUrl = $storageUrl($signaturePath);

    $dobRaw = $meta['id_birthdate'] ?? $meta['id_dob'] ?? $meta['dob'] ?? null;
    $dob = '—';
    if (filled($dobRaw)) {
        try {
            $dob = \Carbon\Carbon::parse($dobRaw)->format('F j, Y');
        } catch (\Carbon\Exceptions\InvalidFormatException) {
            // Ignore invalid optional metadata so the preview remains renderable.
        }
    }

    $tinRaw = preg_replace('/[^0-9]/', '', $meta['id_tin'] ?? $meta['tin'] ?? '');
    $tin = strlen($tinRaw) === 9
        ? substr($tinRaw, 0, 3).'-'.substr($tinRaw, 3, 3).'-'.substr($tinRaw, 6, 3)
        : ($meta['id_tin'] ?? $meta['tin'] ?? '—');
    $gsis = $meta['id_gsis'] ?? $meta['gsis'] ?? '—';
    $emergencyName = $meta['id_emergency_name'] ?? $meta['emergency_contact_name'] ?? 'Emergency Contact Name';
    $emergencyAddress = $meta['id_emergency_address'] ?? $meta['emergency_address'] ?? 'Emergency Contact Address';
    $emergencyPhone = $meta['id_emergency_phone'] ?? $meta['emergency_contact_phone'] ?? '09XXXXXXXXX';
@endphp

<div style="display: flex; flex-wrap: wrap; gap: 24px; justify-content: center; align-items: flex-start; padding: 16px 0; width: 100%; box-sizing: border-box; font-family: ui-sans-serif, system-ui, sans-serif; line-height: 1;">
    <div style="position: relative; width: 320px; height: 505px; overflow: hidden; border-radius: 12px; border: 1px solid #e5e7eb; background-color: #ffffff; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); box-sizing: border-box; font-family: ui-sans-serif, system-ui, sans-serif; line-height: 1;">
        <img src="{{ asset('images/id-template-front.png') }}" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: fill; box-sizing: border-box;" alt="Front Template">

        @if ($photoUrl)
            <img src="{{ $photoUrl }}" style="position: absolute; top: 96px; left: 0.4px; width: 152px; height: 175px; object-fit: cover; box-sizing: border-box;" alt="Photo">
        @endif

        <div style="position: absolute; top: 300px; left: 0; width: 100%; padding: 0 12px; text-align: center; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; box-sizing: border-box; font-size: 20px; font-weight: 700; line-height: 24px; letter-spacing: -0.025em; text-transform: uppercase; color: #000000;">
            {{ $fullName }}
        </div>
        <div style="position: absolute; top: 330px; left: 0; width: 100%; height: 32px; padding: 0 12px; text-align: center; overflow: hidden; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; box-sizing: border-box; font-size: 12px; font-weight: 600; line-height: 1.375; letter-spacing: -0.025em; text-transform: uppercase; color: #000000;">
            {{ $position }}
        </div>

        @if ($signatureUrl)
            <img src="{{ $signatureUrl }}" style="position: absolute; top: 350px; left: 50%; transform: translateX(-50%); width: auto; height: 48px; max-width: 100px; object-fit: contain; box-sizing: border-box;" alt="Signature">
        @endif

        <div style="position: absolute; bottom: 40px; left: 135px; max-width: 165px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 700; color: #000000; font-size: 16px; line-height: 24px; letter-spacing: 0.05em; box-sizing: border-box;">
            {{ $idNumber }}
        </div>

        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-35deg); font-size: 38px; font-weight: 900; color: rgba(220, 38, 38, 0.35); text-transform: uppercase; letter-spacing: 0.12em; pointer-events: none; white-space: nowrap; border: 4px dashed rgba(220, 38, 38, 0.4); padding: 8px 20px; border-radius: 12px; z-index: 30; text-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);">
            SAMPLE ONLY
        </div>
    </div>

    <div style="position: relative; width: 320px; height: 505px; overflow: hidden; border-radius: 12px; border: 1px solid #e5e7eb; background-color: #ffffff; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); box-sizing: border-box; font-family: ui-sans-serif, system-ui, sans-serif; line-height: 1;">
        <img src="{{ asset('images/id-template-back.png') }}" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: fill; box-sizing: border-box;" alt="Back Template">

        <div style="position: absolute; top: 77px; left: 150px; max-width: 130px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; line-height: 16px; color: #000000; box-sizing: border-box;">{{ $dob }}</div>
        <div style="position: absolute; top: 98px; left: 150px; max-width: 130px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; line-height: 16px; color: #000000; box-sizing: border-box;">{{ $tin }}</div>
        <div style="position: absolute; top: 118px; left: 150px; max-width: 130px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; line-height: 16px; color: #000000; box-sizing: border-box;">{{ $gsis }}</div>

        <div style="position: absolute; top: 215px; left: 0; width: 100%; text-align: center; padding: 0 16px; font-size: 12px; line-height: 20px; color: #000000; box-sizing: border-box;">
            <div style="font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; box-sizing: border-box;">{{ $emergencyName }}</div>
            <div style="overflow: hidden; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; box-sizing: border-box;">{{ $emergencyAddress }}</div>
            <div style="font-weight: 700; box-sizing: border-box;">{{ $emergencyPhone }}</div>
        </div>

        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-35deg); font-size: 38px; font-weight: 900; color: rgba(220, 38, 38, 0.35); text-transform: uppercase; letter-spacing: 0.12em; pointer-events: none; white-space: nowrap; border: 4px dashed rgba(220, 38, 38, 0.4); padding: 8px 20px; border-radius: 12px; z-index: 30; text-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);">
            SAMPLE ONLY
        </div>
    </div>
</div>
