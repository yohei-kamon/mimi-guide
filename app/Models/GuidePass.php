<?php

namespace App\Models;

use App\Enums\GuidePassStatus;
use Database\Factories\GuidePassFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $museum_id
 * @property string $token
 * @property GuidePassStatus $status
 * @property int $amount
 * @property string|null $email
 * @property string|null $stripe_checkout_session_id
 * @property Carbon|null $paid_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['token', 'status', 'amount', 'email', 'stripe_checkout_session_id', 'paid_at', 'expires_at'])]
class GuidePass extends Model
{
    /** @use HasFactory<GuidePassFactory> */
    use HasFactory;

    /**
     * How long a pass can be used after the payment.
     */
    public const LIFETIME_HOURS = 24;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GuidePassStatus::class,
            'amount' => 'integer',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Get the museum the pass is for.
     *
     * @return BelongsTo<Museum, $this>
     */
    public function museum(): BelongsTo
    {
        return $this->belongsTo(Museum::class);
    }

    /**
     * Start a pending pass for the museum at its current price.
     */
    public static function startFor(Museum $museum): self
    {
        return $museum->guidePasses()->create([
            'token' => Str::random(40),
            'status' => GuidePassStatus::Pending,
            'amount' => $museum->guide_price,
        ]);
    }

    /**
     * Mark the pass as paid, only while it is still pending, so that the webhook and the
     * completion page can both confirm the payment without doing it twice.
     *
     * Returns whether this call was the one that confirmed the payment.
     */
    public function markAsPaid(?string $email): bool
    {
        $paidAt = now();

        $updated = static::query()
            ->whereKey($this->id)
            ->where('status', GuidePassStatus::Pending)
            ->update([
                'status' => GuidePassStatus::Paid,
                'email' => $email,
                'paid_at' => $paidAt,
                'expires_at' => $paidAt->copy()->addHours(self::LIFETIME_HOURS),
            ]);

        $this->refresh();

        return $updated === 1;
    }

    /**
     * Determine whether the pass is paid and not yet expired.
     */
    public function isValid(): bool
    {
        return $this->status === GuidePassStatus::Paid
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    /**
     * Get the expiry date and time in Japan time for display.
     */
    public function expiresAtLabel(): string
    {
        return $this->expires_at?->copy()->timezone('Asia/Tokyo')->isoFormat('M月D日(ddd) HH:mm') ?? '';
    }

    /**
     * Scope the query to passes that are paid and not yet expired.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function valid(Builder $query): void
    {
        $query->where('status', GuidePassStatus::Paid)->where('expires_at', '>', now());
    }
}
