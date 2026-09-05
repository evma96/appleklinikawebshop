<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\PublicRequest;

use AppleKlinika\Buyback\Application\Command\NewBuybackRequest;
use AppleKlinika\Buyback\Application\LocalDemo\LocalDemoQuestionnaire;
use AppleKlinika\Buyback\Application\Pricing\RepositoryActivePriceBookResolver;
use AppleKlinika\Buyback\Application\Port\Clock;
use AppleKlinika\Buyback\Application\Port\TransactionManager;
use AppleKlinika\Buyback\Domain\Buyback\BuybackStatus;
use AppleKlinika\Buyback\Domain\Buyback\DeviceCategory;
use AppleKlinika\Buyback\Domain\Buyback\DeviceDisplayName;
use AppleKlinika\Buyback\Domain\Buyback\ModelKey;
use AppleKlinika\Buyback\Domain\Buyback\OfferModeConfiguration;
use AppleKlinika\Buyback\Domain\Buyback\RequestSource;
use AppleKlinika\Buyback\Domain\Buyback\ServiceMode;
use AppleKlinika\Buyback\Domain\Buyback\StatusTransitionPolicy;
use AppleKlinika\Buyback\Domain\Buyback\TransitionContext;
use AppleKlinika\Buyback\Domain\Pricing\ConditionAnswerCollection;
use AppleKlinika\Buyback\Domain\Pricing\CurrencyCode;
use AppleKlinika\Buyback\Domain\Pricing\PricingCalculationInput;
use AppleKlinika\Buyback\Domain\Pricing\PricingEngine;
use AppleKlinika\Buyback\Domain\Pricing\PricingModelKey;
use AppleKlinika\Buyback\Domain\Pricing\PricingOutcome;
use AppleKlinika\Buyback\Domain\Pricing\StorageCapacity;
use AppleKlinika\Buyback\Domain\Shared\ActorType;
use AppleKlinika\Buyback\Infrastructure\Identifier\WordPressRequestNumberGenerator;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressBuybackRequestRepository;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressDomainEventStore;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressPublicBuybackRequestStore;

/** Server-side source of truth for public Buyback submissions. */
final class PublicBuybackRequestSubmission
{
    public function __construct(
        private readonly RepositoryActivePriceBookResolver $resolver,
        private readonly PricingEngine $engine,
        private readonly LocalDemoQuestionnaire $questionnaire,
        private readonly WordPressBuybackRequestRepository $requests,
        private readonly WordPressPublicBuybackRequestStore $store,
        private readonly WordPressDomainEventStore $events,
        private readonly WordPressRequestNumberGenerator $numbers,
        private readonly TransactionManager $transactions,
        private readonly Clock $clock,
        private readonly ?OfferModeConfiguration $offerModes = null
    ) {
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,array{label:string,colors:array<string,string>}> $catalog
     */
    public function submit(array $input, array $catalog): PublicBuybackSubmissionResult
    {
        $token = $this->requiredString($input, 'idempotency_token');
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            throw new PublicBuybackSubmissionException('Érvénytelen beküldési azonosító. Frissítsd az oldalt, majd próbáld újra.');
        }
        $tokenHash = hash('sha256', $token);
        $existing = $this->store->findBySubmissionToken($tokenHash);
        if ($existing !== null) {
            return new PublicBuybackSubmissionResult(
                (string) $existing['request_number'],
                (string) $existing['device_display_name'],
                $this->nullableString($existing, 'service_mode'),
                null,
                $this->nullableString($existing, 'service_mode') === null,
                true
            );
        }

        $name = $this->requiredString($input, 'full_name');
        $email = $this->requiredString($input, 'email');
        $phone = $this->requiredString($input, 'phone');
        $note = $this->nullableString($input, 'customer_note');
        if (mb_strlen($name) > 191 || ! is_email($email) || mb_strlen($phone) > 64 || mb_strlen((string) $note) > 1000) {
            throw new PublicBuybackSubmissionException('Ellenőrizd a kapcsolattartási adatokat.');
        }
        if (! $this->isUsablePhone($phone)) {
            throw new PublicBuybackSubmissionException('Adj meg érvényes telefonszámot.');
        }
        if (($input['privacy_acknowledged'] ?? false) !== true) {
            throw new PublicBuybackSubmissionException('Az adatkezelési tájékoztató tudomásulvétele kötelező.');
        }
        if (($input['terms_acknowledged'] ?? false) !== true) {
            throw new PublicBuybackSubmissionException('A felvásárlási feltételek elfogadása kötelező.');
        }

        $modelKey = $this->requiredString($input, 'model_key');
        $storage = (int) ($input['storage_gb'] ?? 0);
        $colorKey = $this->requiredString($input, 'color_key');
        $selectedMode = $this->nullableString($input, 'selected_offer_mode');
        $manualReviewRequested = ($input['manual_review_requested'] ?? false) === true;
        $answers = is_array($input['questionnaire'] ?? null) ? $input['questionnaire'] : [];
        $errors = $this->questionnaire->validate($answers);
        if ($errors !== []) {
            throw new PublicBuybackSubmissionException('A készülék állapotára adott válaszok már nem érvényesek. Számolj újra.');
        }
        $answers = $this->questionnaire->sanitize($answers);
        if ($this->questionnaire->eligibilityError($answers) !== null) {
            throw new PublicBuybackSubmissionException('A megadott készülék jelenleg nem küldhető be automatikus ajánlatként.');
        }
        if (! isset($catalog[$modelKey]['colors'][$colorKey])) {
            throw new PublicBuybackSubmissionException('A kiválasztott modell vagy szín már nem elérhető. Számolj újra.');
        }

        $resolved = $this->resolver->resolveForCurrencyAt(new CurrencyCode('HUF'), $this->clock->now());
        $bookId = $resolved->priceBook->id()?->toInt();
        if ($bookId === null || (int) ($input['price_book_id'] ?? 0) !== $bookId || (int) ($input['price_book_version'] ?? 0) !== $resolved->priceBook->versionNumber()->value()) {
            throw new PublicBuybackSubmissionException('Az árkönyv időközben megváltozott. Kérjük, számold újra az ajánlatot.');
        }
        $configurationExists = false;
        foreach ($resolved->supportedConfigurations as $configuration) {
            if ($configuration->modelKey === $modelKey && $configuration->storageGb === $storage) {
                $configurationExists = true;
                break;
            }
        }
        if (! $configurationExists) {
            throw new PublicBuybackSubmissionException('A kiválasztott modell és tárhely már nem elérhető. Számolj újra.');
        }

        $canonicalAnswers = $this->questionnaire->mapToConditions($answers);
        $questionnaireManualReasons = $this->questionnaire->manualReviewReasons($answers);
        $results = [];
        foreach (array_keys($this->offerModes()->enabled()) as $modeCode) {
            $currentMode = new ServiceMode($modeCode);
            $calculated = $this->engine->calculate($resolved->priceBook, $resolved->enabledRules, new PricingCalculationInput(
                    new DeviceCategory('iphone'),
                    new PricingModelKey($modelKey),
                    new StorageCapacity($storage),
                    ConditionAnswerCollection::fromAssociative($canonicalAnswers),
                    $currentMode,
                    $this->questionnaire->affectedPartKeys($answers)
                ));
            $results[$modeCode] = $this->manualResultIfRequired($resolved->priceBook, $currentMode, $questionnaireManualReasons, $calculated);
        }
        $representative = $results[ServiceMode::FAST_ONLINE] ?? reset($results);
        if ($representative === null || in_array($representative->outcome->code(), [PricingOutcome::REJECTED, PricingOutcome::CONFIGURATION_ERROR], true)) {
            throw new PublicBuybackSubmissionException('Ehhez a készülékhez most nem küldhető be automatikus felvásárlási igény.');
        }
        $isManualReview = $representative->outcome->code() === PricingOutcome::MANUAL_REVIEW;
        if ($manualReviewRequested !== $isManualReview || ($isManualReview && $selectedMode !== null)) {
            throw new PublicBuybackSubmissionException('A felvásárlási igény állapota megváltozott. Kérjük, számold újra az ajánlatot.');
        }
        if (! $isManualReview && $selectedMode === null) {
            throw new PublicBuybackSubmissionException('Válassz egy érvényes ajánlattípust.');
        }
        try {
            $mode = $isManualReview ? null : new ServiceMode((string) $selectedMode);
        } catch (\Throwable) {
            throw new PublicBuybackSubmissionException('Válassz egy érvényes ajánlattípust.');
        }
        if (! $isManualReview && ! $this->offerModes()->isEnabled($mode->code())) {
            throw new PublicBuybackSubmissionException('A kiválasztott ajánlattípus jelenleg nem érhető el. Kérjük, számold újra az ajánlatot.');
        }
        $selected = $isManualReview ? null : ($results[$mode->code()] ?? null);
        if (! $isManualReview && ($selected === null || $selected->outcome->code() !== PricingOutcome::OFFERED)) {
            throw new PublicBuybackSubmissionException('A kiválasztott ajánlat már nem érhető el. Kérjük, számold újra az ajánlatot.');
        }

        $device = (string) $catalog[$modelKey]['label'] . ' · ' . ($storage === 1024 ? '1 TB' : $storage . ' GB') . ' · ' . $catalog[$modelKey]['colors'][$colorKey];
        $publicSummary = $this->questionnaire->summary(
            $answers,
            (string) $catalog[$modelKey]['label'],
            $storage === 1024 ? '1 TB' : $storage . ' GB',
            (string) $catalog[$modelKey]['colors'][$colorKey]
        );
        $now = $this->clock->now();
        $timestamp = $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $amount = $selected?->finalAmount?->amount();
        $manualReasons = $isManualReview ? $this->publicManualReasons($representative, $answers) : [];

        try {
            return $this->transactions->transactional(function () use ($tokenHash, $name, $email, $phone, $note, $modelKey, $device, $mode, $now, $timestamp, $canonicalAnswers, $publicSummary, $resolved, $results, $amount, $input, $isManualReview, $manualReasons): PublicBuybackSubmissionResult {
                $request = $this->requests->insert(new NewBuybackRequest(
                    $this->numbers->generate(),
                    null,
                    new DeviceCategory('iphone'),
                    new ModelKey($modelKey),
                    new DeviceDisplayName($device),
                    $mode,
                    null,
                    new RequestSource(RequestSource::NATIVE),
                    null,
                    $now
                ));
                $this->store->savePublicData($request->id(), $tokenHash, $name, $email, $phone, $note, $timestamp);
                $request->transitionTo(new BuybackStatus(BuybackStatus::SUBMITTED), new StatusTransitionPolicy(), new TransitionContext(new ActorType(ActorType::CUSTOMER), $now, null, false, false, false, false, $tokenHash));
                $this->requests->save($request, new \AppleKlinika\Buyback\Domain\Shared\AggregateVersion(0));
                $this->events->publish(...$request->releasePendingEvents());
                $this->store->saveInitialSnapshot($request->id(), [
                    'submitted_at' => $timestamp,
                    'device' => ['model_key' => $modelKey, 'public_name' => $device, 'storage_gb' => (int) $input['storage_gb'], 'color_key' => (string) $input['color_key']],
                    'customer' => ['full_name' => $name, 'email' => $email, 'phone' => $phone, 'note' => $note],
                    'questionnaire' => ['canonical_answers' => $canonicalAnswers, 'public_answers' => $publicSummary],
                    'price_book' => ['id' => $resolved->priceBook->id()?->toInt(), 'version' => $resolved->priceBook->versionNumber()->value(), 'rules_hash' => hash('sha256', serialize($resolved->enabledRules))],
                    'offers' => array_map(static fn ($result): array => $result->toArray(), $results),
                    'effective_rule_sources' => $this->effectiveRuleSources($results),
                    'calculation' => ['status' => $isManualReview ? PricingOutcome::MANUAL_REVIEW : PricingOutcome::OFFERED, 'reasons' => $manualReasons],
                    'calculation_status' => $isManualReview ? PricingOutcome::MANUAL_REVIEW : PricingOutcome::OFFERED,
                    'selected_offer_mode' => $mode?->code(),
                    'selected_amount' => $amount,
                    'selected_final_amount_minor' => $amount,
                    'manual_review_requested' => $isManualReview,
                    'manual_review_requested_at' => $isManualReview ? $timestamp : null,
                    'privacy' => ['policy_url' => (string) ($input['privacy_url'] ?? ''), 'policy_marker' => (string) ($input['privacy_marker'] ?? ''), 'acknowledged' => true],
                    'terms' => ['url' => (string) ($input['terms_url'] ?? ''), 'acknowledged' => true],
                    'marketing_consent' => ($input['marketing_consent'] ?? false) === true,
                ], $timestamp);
                return new PublicBuybackSubmissionResult($request->requestNumber()->value(), $device, $mode?->code(), $amount, $isManualReview, false, $manualReasons);
            });
        } catch (\Throwable $exception) {
            $existing = $this->store->findBySubmissionToken($tokenHash);
            if ($existing !== null) {
                $existingMode = $this->nullableString($existing, 'service_mode');
                return new PublicBuybackSubmissionResult((string) $existing['request_number'], (string) $existing['device_display_name'], $existingMode, null, $existingMode === null, true);
            }
            if ($exception instanceof PublicBuybackSubmissionException) {
                throw $exception;
            }
            throw new PublicBuybackSubmissionException('A felvásárlási igény mentése most nem sikerült. Kérjük, próbáld újra.', 0, $exception);
        }
    }

    private function requiredString(array $input, string $key): string
    {
        $value = isset($input[$key]) && is_scalar($input[$key]) ? trim((string) $input[$key]) : '';
        if ($value === '') {
            throw new PublicBuybackSubmissionException('Tölts ki minden kötelező mezőt.');
        }
        return $value;
    }

    private function nullableString(array $input, string $key): ?string
    {
        $value = isset($input[$key]) && is_scalar($input[$key]) ? trim((string) $input[$key]) : '';
        return $value === '' ? null : $value;
    }

    private function offerModes(): OfferModeConfiguration
    {
        return $this->offerModes ?? OfferModeConfiguration::defaults();
    }

    private function isUsablePhone(string $phone): bool
    {
        return preg_match('/^[+0-9 ()-]{7,64}$/', $phone) === 1 && preg_match_all('/[0-9]/', $phone) >= 7;
    }

    private function manualResultIfRequired($book, ServiceMode $mode, array $questionnaireReasons, \AppleKlinika\Buyback\Domain\Pricing\PricingCalculationResult $calculated): \AppleKlinika\Buyback\Domain\Pricing\PricingCalculationResult
    {
        if ($calculated->outcome->code() !== PricingOutcome::MANUAL_REVIEW && $questionnaireReasons === []) {
            return $calculated;
        }

        if ($calculated->outcome->code() === PricingOutcome::REJECTED || $calculated->outcome->code() === PricingOutcome::CONFIGURATION_ERROR) {
            return $calculated;
        }

        return \AppleKlinika\Buyback\Domain\Pricing\PricingCalculationResult::manualReview(
            $book,
            $mode,
            array_merge($calculated->reasonCodes, $questionnaireReasons),
            $calculated->matchedRules,
            $calculated->breakdown,
            $calculated->calculatorVersion
        );
    }

    /** @param array<string,\AppleKlinika\Buyback\Domain\Pricing\PricingCalculationResult> $results @return array<string,list<array{rule_code:string,rule_kind:string,source:string,reason:?string,condition_key:?string,affected_component_key:?string}>> */
    private function effectiveRuleSources(array $results): array
    {
        $sources = [];
        foreach ($results as $mode => $result) {
            $sources[$mode] = array_map(static function ($rule): array {
                return [
                    'rule_code' => $rule->ruleCode,
                    'rule_kind' => $rule->ruleKind,
                    'source' => $rule->source,
                    'reason' => $rule->publicLabel,
                    'condition_key' => $rule->conditionKey,
                    'affected_component_key' => $rule->affectedComponentKey,
                ];
            }, $result->matchedRules);
        }
        return $sources;
    }

    /** @return list<string> */
    private function publicManualReasons(\AppleKlinika\Buyback\Domain\Pricing\PricingCalculationResult $result, array $answers): array
    {
        $reasons = [];
        foreach ($result->matchedRules as $rule) {
            $reasons[] = $this->questionnaire->publicManualReviewReason(
                $rule->conditionKey,
                $rule->publicLabel ?? '',
                $answers,
                $rule->affectedComponentKey
            );
        }
        $matchedCodes = array_map(static fn ($rule): string => $rule->ruleCode, $result->matchedRules);
        foreach ($result->reasonCodes as $reason) {
            if ($reason === '' || in_array($reason, $matchedCodes, true)) {
                continue;
            }
            $reasons[] = in_array($reason, ['below_minimum_offer', 'below_model_minimum_offer'], true)
                ? 'Az előzetes ajánlat pontosításához személyes bevizsgálás szükséges.'
                : $this->questionnaire->publicManualReviewReason(null, $reason, $answers);
        }

        return array_values(array_unique(array_filter($reasons, static fn (string $reason): bool => $reason !== '')));
    }
}
