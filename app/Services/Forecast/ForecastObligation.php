<?php

namespace App\Services\Forecast;

/**
 * One classified commitment in the cash-flow forecast. Amounts are integer minor units so
 * aggregation never touches floats; the identity is stable per source record and due date.
 */
final class ForecastObligation
{
    public const TYPE_LOAN = 'loan';
    public const TYPE_CREDIT_CARD = 'credit-card';
    public const TYPE_SUBSCRIPTION = 'subscription';

    // Posting states: what is known about the account balance for this obligation.
    public const STATE_UNPOSTED = 'unposted';
    public const STATE_POSTED = 'posted';
    public const STATE_POSTING_TRASHED = 'posting_trashed';
    public const STATE_CARD_CHARGE = 'card_charge';
    public const STATE_UNRESOLVED = 'unresolved';

    // Coverage / exclusion reasons.
    public const REASON_ALREADY_IN_BALANCE = 'already_in_balance';
    public const REASON_POSTING_SOFT_DELETED = 'posting_soft_deleted';
    public const REASON_CARD_CHARGE_RECORDED = 'card_charge_recorded';
    public const REASON_CARD_CHARGE_PROJECTED = 'card_charge_projected';
    public const REASON_SOURCE_UNAVAILABLE = 'source_account_unavailable';
    public const REASON_UNKNOWN_CURRENCY = 'unknown_currency';
    public const REASON_OVERDUE = 'overdue_unsettled';
    public const REASON_PAID_WITHOUT_POSTING = 'paid_without_posting';
    public const REASON_ACCOUNT_EXCLUDED = 'source_account_excluded';

    public function __construct(
        public readonly string $type,
        public readonly int $sourceId,
        public readonly string $dueDate,
        public readonly string $name,
        public readonly ?int $accountId,
        public readonly ?int $creditCardId,
        public readonly ?string $currency,
        public readonly int $faceMinor,
        public readonly int $cashOutflowMinor,
        public readonly string $postingState,
        public readonly ?string $reason,
        public readonly bool $overdue,
    ) {}

    public function id(): string
    {
        return "{$this->type}:{$this->sourceId}:{$this->dueDate}";
    }

    public function withExclusion(string $reason): self
    {
        return new self(
            $this->type, $this->sourceId, $this->dueDate, $this->name, $this->accountId,
            $this->creditCardId, $this->currency, $this->faceMinor, 0, $this->postingState,
            $reason, $this->overdue,
        );
    }

    /**
     * Whether this obligation leaves the estimate unable to claim completeness.
     */
    public function isCoverageGap(): bool
    {
        if ($this->reason === self::REASON_ACCOUNT_EXCLUDED) {
            return false;
        }

        return $this->overdue || in_array($this->reason, [
            self::REASON_POSTING_SOFT_DELETED,
            self::REASON_PAID_WITHOUT_POSTING,
            self::REASON_CARD_CHARGE_PROJECTED,
            self::REASON_SOURCE_UNAVAILABLE,
            self::REASON_UNKNOWN_CURRENCY,
        ], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'type' => $this->type,
            'source_id' => $this->sourceId,
            'name' => $this->name,
            'due_date' => $this->dueDate,
            'currency' => $this->currency,
            'face_amount' => self::format($this->faceMinor),
            'cash_outflow_amount' => self::format($this->cashOutflowMinor),
            'posting_state' => $this->postingState,
            'reason' => $this->reason,
            'overdue' => $this->overdue,
        ];
    }

    public static function toMinor(float|string|null $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function format(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';
        $abs = abs($minor);

        return $sign . intdiv($abs, 100) . '.' . str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }
}
