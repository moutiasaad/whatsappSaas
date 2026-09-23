<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'phone_e164', 'display_name', 'profile_pic_url', 'tags', 'notes', 'first_contact_at'];
    protected $casts = [
        'tenant_id'         => 'integer',
        'tags'              => 'array',
        'first_contact_at'  => 'datetime',
    ];

    // Expose the computed phone label in every AJAX/JSON response so the
    // front-end doesn't have to reimplement the LID-vs-MSISDN normalisation
    // that getDisplayPhoneAttribute already does. phone_country / phone_flag /
    // phone_formatted give the customers table a pro-looking cell without
    // any client-side parsing.
    protected $appends = [
        'display_phone', 'phone_kind',
        'phone_country', 'phone_flag', 'phone_formatted',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function conversations(): HasMany { return $this->hasMany(Conversation::class); }

    public function getDisplayNameOrPhoneAttribute(): string
    {
        return $this->display_name ?: ($this->displayPhone ?: '—');
    }

    public function getDisplayPhoneAttribute(): string
    {
        $phone = (string) ($this->phone_e164 ?? '');
        if ($phone === '') return '';

        // @lid JIDs are Meta device IDs — not real phone numbers
        if (str_contains($phone, '@lid')) {
            return '';
        }

        // Strip any remaining JID suffix (@s.whatsapp.net, @c.us, etc.)
        $number = (string) preg_replace('/@\S+/', '', $phone);
        if ($number === '') return '';

        return ctype_digit($number) ? '+' . $number : $number;
    }

    /**
     * How to render the phone cell. The three states are treated distinctly
     * in the UI: 'phone' is the E.164 label, 'lid' shows a "Hidden number"
     * badge (a real customer identity we just can't display as a number),
     * 'empty' is a plain dash.
     *
     * @return 'phone'|'lid'|'empty'
     */
    public function getPhoneKindAttribute(): string
    {
        $raw = (string) ($this->phone_e164 ?? '');
        if ($raw === '') return 'empty';
        if (str_contains($raw, '@lid')) return 'lid';
        return 'phone';
    }

    /**
     * ISO 3166-1 alpha-2 country code inferred from the dialling prefix.
     * Null when the phone is a LID / empty / prefix isn't in the map.
     * Longest-prefix match wins so a 3-digit code (216) doesn't get
     * short-circuited by a 1-digit one (2).
     */
    public function getPhoneCountryAttribute(): ?string
    {
        if ($this->phone_kind !== 'phone') return null;

        $digits = ltrim($this->display_phone ?: '', '+');
        if ($digits === '') return null;

        foreach ([3, 2, 1] as $len) {
            $prefix = substr($digits, 0, $len);
            if (isset(self::DIALLING_PREFIXES[$prefix])) {
                return self::DIALLING_PREFIXES[$prefix];
            }
        }
        return null;
    }

    /**
     * Regional-indicator emoji for the inferred country, e.g. "🇸🇦" for SA.
     * Empty string when no country resolves — the cell then hides the
     * leading flag column so the row alignment stays even.
     */
    public function getPhoneFlagAttribute(): string
    {
        $code = $this->phone_country;
        if (!$code || strlen($code) !== 2) return '';

        $code = strtoupper($code);
        return mb_chr(ord($code[0]) + 127397, 'UTF-8') . mb_chr(ord($code[1]) + 127397, 'UTF-8');
    }

    /**
     * Space-grouped E.164 for readability — "+963 943 980 404" instead of
     * "+963943980404". Universal 3-from-the-right grouping (works for any
     * country code length without a per-country format table).
     */
    public function getPhoneFormattedAttribute(): string
    {
        $phone = $this->display_phone ?: '';
        if ($phone === '') return '';

        $digits = ltrim($phone, '+');
        if ($digits === '') return $phone;

        $rev = strrev($digits);
        $chunks = str_split($rev, 3);
        return '+' . strrev(implode(' ', array_map('strrev', $chunks)));
    }

    /**
     * Common WhatsApp-heavy dialling prefixes → ISO alpha-2.
     * Longer prefixes matched first (see getPhoneCountryAttribute).
     * MENA + top-10 global coverage; unknown prefixes fall through to
     * "no flag", which the UI renders cleanly.
     */
    private const DIALLING_PREFIXES = [
        // 3-digit
        '212' => 'MA', '213' => 'DZ', '216' => 'TN', '218' => 'LY',
        '249' => 'SD', '966' => 'SA', '971' => 'AE', '973' => 'BH',
        '974' => 'QA', '965' => 'KW', '968' => 'OM', '967' => 'YE',
        '963' => 'SY', '961' => 'LB', '962' => 'JO', '964' => 'IQ',
        '970' => 'PS', '972' => 'IL', '234' => 'NG', '254' => 'KE',
        '256' => 'UG', '233' => 'GH', '225' => 'CI', '221' => 'SN',
        // 2-digit
        '20' => 'EG', '27' => 'ZA', '30' => 'GR', '31' => 'NL',
        '32' => 'BE', '33' => 'FR', '34' => 'ES', '39' => 'IT',
        '40' => 'RO', '41' => 'CH', '43' => 'AT', '44' => 'GB',
        '45' => 'DK', '46' => 'SE', '47' => 'NO', '48' => 'PL',
        '49' => 'DE', '51' => 'PE', '52' => 'MX', '53' => 'CU',
        '54' => 'AR', '55' => 'BR', '56' => 'CL', '57' => 'CO',
        '58' => 'VE', '60' => 'MY', '61' => 'AU', '62' => 'ID',
        '63' => 'PH', '64' => 'NZ', '65' => 'SG', '66' => 'TH',
        '81' => 'JP', '82' => 'KR', '84' => 'VN', '86' => 'CN',
        '90' => 'TR', '91' => 'IN', '92' => 'PK', '93' => 'AF',
        '94' => 'LK', '95' => 'MM', '98' => 'IR',
        // 1-digit
        '1' => 'US', '7' => 'RU',
    ];
}
