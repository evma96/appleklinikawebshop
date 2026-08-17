<?php

declare(strict_types=1);

namespace Appleklinika\BackOffice\Domain;

use InvalidArgumentException;

final class FulfilmentWorkflow
{
    public const META_KEY = '_appleklinika_backoffice_state';

    public const NEW = 'new';
    public const PREPARATION = 'preparation';
    public const PACKING = 'packing';
    public const READY_FOR_SHIPPING = 'ready_for_shipping';
    public const HANDED_TO_GLS = 'handed_to_gls';
    public const READY_FOR_PICKUP = 'ready_for_pickup';
    public const PICKED_UP = 'picked_up';
    public const PROBLEM = 'problem';

    // These values remain readable on existing WooCommerce orders and in activity history.
    public const STARTED = 'started';
    public const DEVICE_CHECKED = 'device_checked';
    public const PACKED = 'packed';
    public const DOCUMENTS_READY = 'documents_ready';
    public const LABEL_CREATED = 'label_created';
    public const COMPLETED = 'completed';

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::NEW => 'Új',
            self::PREPARATION => 'Előkészítés alatt',
            self::PACKING => 'Csomagolás alatt',
            self::READY_FOR_SHIPPING => 'Szállításra előkészítve',
            self::HANDED_TO_GLS => 'Átadva a GLS-nek',
            self::READY_FOR_PICKUP => 'Átvételre előkészítve',
            self::PICKED_UP => 'Átvéve',
            self::PROBLEM => 'Problémás',
            self::STARTED => 'Előkészítés alatt (régi állapot)',
            self::DEVICE_CHECKED => 'Készülék ellenőrizve (régi állapot)',
            self::PACKED => 'Becsomagolva (régi állapot)',
            self::DOCUMENTS_READY => 'Dokumentumok előkészítve (régi állapot)',
            self::LABEL_CREATED => 'GLS címke elkészült (régi állapot)',
            self::COMPLETED => 'Teljesítve (régi állapot)',
        ];
    }

    /** @return array<string, string> */
    public static function actions(): array
    {
        return [
            'start' => 'Feldolgozás megkezdése',
            'start_packing' => 'Csomagolás megkezdése',
            'packing_completed' => 'Csomagolás kész',
            'create_label' => 'GLS címke létrehozása',
            'handed_to_gls' => 'Átadva a GLS-nek',
            'prepare_pickup' => 'Átvételre előkészítés',
            'picked_up' => 'Átvette a vásárló',
            'problem' => 'Problémásnak jelölés',
            'resume' => 'Feldolgozás folytatása',
            'device_checked' => 'Készülék ellenőrizve (régi művelet)',
            'prepare_documents' => 'Dokumentumok előkészítése (régi művelet)',
            'complete' => 'Belső teljesítés lezárása (régi művelet)',
        ];
    }

    public static function state(string $state): string
    {
        return self::legacyStateMap()[$state] ?? (array_key_exists($state, self::labels()) ? $state : self::NEW);
    }

    public static function transition(string $currentState, string $action, string $deliveryMode = DeliveryMode::GLS): string
    {
        $currentState = self::stateForDeliveryMode($currentState, $deliveryMode);
        $glsTransitions = [
            self::NEW => ['start' => self::PREPARATION, 'problem' => self::PROBLEM],
            self::PREPARATION => ['start_packing' => self::PACKING, 'problem' => self::PROBLEM],
            self::PACKING => ['packing_completed' => self::READY_FOR_SHIPPING, 'problem' => self::PROBLEM],
            self::READY_FOR_SHIPPING => ['create_label' => self::READY_FOR_SHIPPING, 'handed_to_gls' => self::HANDED_TO_GLS, 'problem' => self::PROBLEM],
            self::HANDED_TO_GLS => [],
            self::PROBLEM => ['resume' => self::PREPARATION],
        ];
        $pickupTransitions = [
            self::NEW => ['start' => self::PREPARATION, 'problem' => self::PROBLEM],
            self::PREPARATION => ['prepare_pickup' => self::READY_FOR_PICKUP, 'problem' => self::PROBLEM],
            self::READY_FOR_PICKUP => ['picked_up' => self::PICKED_UP, 'problem' => self::PROBLEM],
            self::PICKED_UP => [],
            self::PROBLEM => ['resume' => self::PREPARATION],
        ];
        $transitions = $deliveryMode === DeliveryMode::PICKUP ? $pickupTransitions : $glsTransitions;

        if (! isset($transitions[$currentState][$action])) {
            throw new InvalidArgumentException('Ez a művelet a jelenlegi teljesítési állapotból nem végezhető el.');
        }

        return $transitions[$currentState][$action];
    }

    /** @return array<string, list<string>> */
    public static function queueStates(): array
    {
        return [
            'new' => [self::NEW],
            'preparation' => [self::PREPARATION, self::STARTED, self::DEVICE_CHECKED],
            'packing' => [self::PACKING],
            'ready_for_shipping' => [self::READY_FOR_SHIPPING, self::READY_FOR_PICKUP, self::PACKED, self::DOCUMENTS_READY, self::LABEL_CREATED],
            'handed_to_gls' => [self::HANDED_TO_GLS, self::COMPLETED, self::PICKED_UP],
            'problem' => [self::PROBLEM],
        ];
    }

    /** @return array<string, string> */
    public static function customerProgressLabels(string $deliveryMode = DeliveryMode::GLS): array
    {
        if ($deliveryMode === DeliveryMode::PICKUP) {
            return [
                self::NEW => 'Rendelés beérkezett',
                self::PREPARATION => 'Előkészítés alatt',
                self::READY_FOR_PICKUP => 'Átvételre előkészítve',
                self::PICKED_UP => 'Átvéve',
            ];
        }

        return [
            self::NEW => 'Rendelés beérkezett',
            self::PREPARATION => 'Előkészítés alatt',
            self::PACKING => 'Csomagolás alatt',
            self::READY_FOR_SHIPPING => 'Szállításra előkészítve',
            self::HANDED_TO_GLS => 'Átadva a futárszolgálatnak',
        ];
    }

    /** @param list<array{to?:string}> $history */
    public static function customerProgressState(string $state, array $history = [], string $deliveryMode = DeliveryMode::GLS): string
    {
        $state = self::stateForDeliveryMode($state, $deliveryMode);
        if ($state !== self::PROBLEM) {
            return $state;
        }

        foreach (array_reverse($history) as $entry) {
            $previousState = self::stateForDeliveryMode((string) ($entry['to'] ?? ''), $deliveryMode);
            if ($previousState !== self::PROBLEM) {
                return self::customerProgressState($previousState, [], $deliveryMode);
            }
        }

        return self::NEW;
    }

    public static function primaryAction(string $state, string $deliveryMode = DeliveryMode::GLS, bool $hasGlsLabel = false): ?string
    {
        $state = self::stateForDeliveryMode($state, $deliveryMode);
        if ($deliveryMode === DeliveryMode::PICKUP) {
            return match ($state) {
                self::NEW => 'start',
                self::PREPARATION => 'prepare_pickup',
                self::READY_FOR_PICKUP => 'picked_up',
                self::PROBLEM => 'resume',
                default => null,
            };
        }

        if ($deliveryMode !== DeliveryMode::GLS) {
            return null;
        }

        return match ($state) {
            self::NEW => 'start',
            self::PREPARATION => 'start_packing',
            self::PACKING => 'packing_completed',
            self::READY_FOR_SHIPPING => $hasGlsLabel ? 'handed_to_gls' : 'create_label',
            self::PROBLEM => 'resume',
            default => null,
        };
    }

    public static function stateForDeliveryMode(string $state, string $deliveryMode): string
    {
        $state = self::state($state);
        if ($deliveryMode !== DeliveryMode::PICKUP) {
            return $state;
        }

        return match ($state) {
            self::READY_FOR_SHIPPING => self::READY_FOR_PICKUP,
            self::HANDED_TO_GLS => self::PICKED_UP,
            default => $state,
        };
    }

    /** @return list<string> */
    public static function operationalOrderStatuses(): array
    {
        return ['pending', 'on-hold', 'processing'];
    }

    /** @param list<array{user_id?:int,user?:string}> $activity @return list<array{user_id:int,user:string,count:int}> */
    public static function employeeDailyCounts(array $activity): array
    {
        $counts = [];
        foreach ($activity as $entry) {
            $userId = max(0, (int) ($entry['user_id'] ?? 0));
            $user = trim((string) ($entry['user'] ?? 'Ismeretlen felhasználó'));
            $key = $userId > 0 ? (string) $userId : $user;
            if (! isset($counts[$key])) {
                $counts[$key] = ['user_id' => $userId, 'user' => $user, 'count' => 0];
            }
            ++$counts[$key]['count'];
        }

        usort($counts, static fn (array $left, array $right): int => $right['count'] <=> $left['count'] ?: strcmp($left['user'], $right['user']));
        return array_values($counts);
    }

    /** @return array<string, string> */
    private static function legacyStateMap(): array
    {
        return [
            self::STARTED => self::PREPARATION,
            self::DEVICE_CHECKED => self::PREPARATION,
            self::PACKED => self::READY_FOR_SHIPPING,
            self::DOCUMENTS_READY => self::READY_FOR_SHIPPING,
            self::LABEL_CREATED => self::READY_FOR_SHIPPING,
            self::COMPLETED => self::HANDED_TO_GLS,
        ];
    }
}
