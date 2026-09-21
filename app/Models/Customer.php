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
    // that getDisplayPhoneAttribute already does.
    protected $appends = ['display_phone', 'phone_kind'];

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
}
