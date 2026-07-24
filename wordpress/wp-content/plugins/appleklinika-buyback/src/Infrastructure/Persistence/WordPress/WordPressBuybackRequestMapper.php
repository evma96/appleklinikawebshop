<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\Persistence\WordPress;

use AppleKlinika\Buyback\Application\Command\NewBuybackRequest;
use AppleKlinika\Buyback\Domain\Buyback\BuybackRequest;
use AppleKlinika\Buyback\Domain\Buyback\BuybackRequestId;
use AppleKlinika\Buyback\Domain\Buyback\BuybackStatus;
use AppleKlinika\Buyback\Domain\Buyback\CustomerId;
use AppleKlinika\Buyback\Domain\Buyback\DeviceCategory;
use AppleKlinika\Buyback\Domain\Buyback\DeviceDisplayName;
use AppleKlinika\Buyback\Domain\Buyback\HandoverMethod;
use AppleKlinika\Buyback\Domain\Buyback\LegacyReference;
use AppleKlinika\Buyback\Domain\Buyback\ModelKey;
use AppleKlinika\Buyback\Domain\Buyback\RequestNumber;
use AppleKlinika\Buyback\Domain\Buyback\RequestSource;
use AppleKlinika\Buyback\Domain\Buyback\ServiceMode;
use AppleKlinika\Buyback\Domain\Shared\AggregateVersion;
use AppleKlinika\Buyback\Infrastructure\Persistence\Exception\PersistenceException;

final class WordPressBuybackRequestMapper
{
    private readonly \DateTimeZone $utc;

    public function __construct()
    {
        $this->utc = new \DateTimeZone('UTC');
    }

    /** @param array<string, mixed>|object $row */
    public function toDomain(array|object $row): BuybackRequest
    {
        $values = is_object($row) ? get_object_vars($row) : $row;

        try {
            return BuybackRequest::reconstitute(
                new BuybackRequestId($this->positiveInteger($values, 'id')),
                new RequestNumber($this->requiredString($values, 'request_number')),
                $this->nullablePositiveInteger($values, 'customer_id') === null
                    ? null
                    : new CustomerId($this->nullablePositiveInteger($values, 'customer_id')),
                new DeviceCategory($this->requiredString($values, 'category')),
                new ModelKey($this->requiredString($values, 'model_key')),
                new DeviceDisplayName($this->requiredString($values, 'device_display_name')),
                $this->nullableString($values, 'service_mode') === null
                    ? null
                    : new ServiceMode($this->nullableString($values, 'service_mode')),
                $this->nullableString($values, 'handover_method') === null
                    ? null
                    : new HandoverMethod($this->nullableString($values, 'handover_method')),
                new BuybackStatus($this->requiredString($values, 'status')),
                new RequestSource($this->requiredString($values, 'source')),
                $this->nullableString($values, 'legacy_reference') === null
                    ? null
                    : new LegacyReference($this->nullableString($values, 'legacy_reference')),
                new AggregateVersion($this->nonNegativeInteger($values, 'version')),
                $this->dateTime($values, 'created_at'),
                $this->dateTime($values, 'updated_at')
            );
        } catch (PersistenceException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new PersistenceException(
                'Persisted buyback request contains unsupported domain data.',
                0,
                $exception
            );
        }
    }

    /** @return array<string, int|string|null> */
    public function insertValues(NewBuybackRequest $request): array
    {
        $timestamp = $this->formatDateTime($request->createdAt);

        return [
            'request_number' => $request->requestNumber->value(),
            'customer_id' => $request->customerId?->toInt(),
            'category' => $request->category->code(),
            'model_key' => $request->modelKey->value(),
            'device_display_name' => $request->deviceDisplayName->value(),
            'service_mode' => $request->serviceMode?->code(),
            'handover_method' => $request->handoverMethod?->code(),
            'status' => BuybackStatus::DRAFT,
            'source' => $request->source->code(),
            'legacy_reference' => $request->legacyReference?->value(),
            'demo_marker' => $request->demoMarker(),
            'version' => 0,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    /** @return array<string, int|string|null> */
    public function updateValues(BuybackRequest $request): array
    {
        return [
            'customer_id' => $request->customerId()?->toInt(),
            'service_mode' => $request->serviceMode()?->code(),
            'handover_method' => $request->handoverMethod()?->code(),
            'status' => $request->status()->code(),
            'version' => $request->version()->value(),
            'updated_at' => $this->formatDateTime($request->updatedAt()),
        ];
    }

    public function formatDateTime(\DateTimeImmutable $dateTime): string
    {
        return $dateTime->setTimezone($this->utc)->format('Y-m-d H:i:s');
    }

    /** @param array<string, mixed> $values */
    private function requiredString(array $values, string $key): string
    {
        if (! array_key_exists($key, $values) || ! is_scalar($values[$key])) {
            throw new PersistenceException("Missing or invalid persisted field: {$key}.");
        }

        $value = (string) $values[$key];

        if ($value === '') {
            throw new PersistenceException("Persisted field cannot be empty: {$key}.");
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    private function nullableString(array $values, string $key): ?string
    {
        if (! array_key_exists($key, $values) || $values[$key] === null) {
            return null;
        }

        if (! is_scalar($values[$key])) {
            throw new PersistenceException("Invalid persisted field: {$key}.");
        }

        $value = (string) $values[$key];

        return $value === '' ? null : $value;
    }

    /** @param array<string, mixed> $values */
    private function positiveInteger(array $values, string $key): int
    {
        $value = $this->integer($values, $key);

        if ($value <= 0) {
            throw new PersistenceException("Persisted field must be positive: {$key}.");
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    private function nullablePositiveInteger(array $values, string $key): ?int
    {
        if (! array_key_exists($key, $values) || $values[$key] === null || $values[$key] === '') {
            return null;
        }

        return $this->positiveInteger($values, $key);
    }

    /** @param array<string, mixed> $values */
    private function nonNegativeInteger(array $values, string $key): int
    {
        $value = $this->integer($values, $key);

        if ($value < 0) {
            throw new PersistenceException("Persisted field cannot be negative: {$key}.");
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    private function integer(array $values, string $key): int
    {
        if (! array_key_exists($key, $values)) {
            throw new PersistenceException("Missing persisted field: {$key}.");
        }

        $raw = $values[$key];

        if (is_int($raw)) {
            return $raw;
        }

        if (! is_string($raw) || preg_match('/^\d+$/', $raw) !== 1) {
            throw new PersistenceException("Invalid persisted integer field: {$key}.");
        }

        return (int) $raw;
    }

    /** @param array<string, mixed> $values */
    private function dateTime(array $values, string $key): \DateTimeImmutable
    {
        $raw = $this->requiredString($values, $key);
        $dateTime = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $raw, $this->utc);
        $errors = \DateTimeImmutable::getLastErrors();

        if (
            ! $dateTime instanceof \DateTimeImmutable
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $dateTime->format('Y-m-d H:i:s') !== $raw
        ) {
            throw new PersistenceException("Invalid UTC timestamp in persisted field: {$key}.");
        }

        return $dateTime;
    }
}
