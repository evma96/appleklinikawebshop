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
        private readonly Clock $clock
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
                (string) $existing['service_mode'],
                null,
                false,
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

        $modelKey = $this->requiredString($input, 'model_key');
        $storage = (int) ($input['storage_gb'] ?? 0);
        $colorKey = $this->requiredString($input, 'color_key');
        $selectedMode = $this->requiredString($input, 'selected_offer_mode');
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

        try {
            $mode = new ServiceMode($selectedMode);
        } catch (\Throwable) {
            throw new PublicBuybackSubmissionException('Válassz egy érvényes ajánlattípust.');
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
        $manualReasons = $this->questionnaire->manualReviewReasons($answers);
        $results = [];
        foreach (ServiceMode::supportedCodes() as $modeCode) {
            $currentMode = new ServiceMode($modeCode);
            $results[$modeCode] = $manualReasons !== []
                ? \AppleKlinika\Buyback\Domain\Pricing\PricingCalculationResult::manualReview($resolved->priceBook, $currentMode, $manualReasons)
                : $this->engine->calculate($resolved->priceBook, $resolved->enabledRules, new PricingCalculationInput(
                    new DeviceCategory('iphone'),
                    new PricingModelKey($modelKey),
                    new StorageCapacity($storage),
                    ConditionAnswerCollection::fromAssociative($canonicalAnswers),
                    $currentMode
                ));
        }
        $selected = $results[$mode->code()] ?? null;
        if ($selected === null || in_array($selected->outcome->code(), [PricingOutcome::REJECTED, PricingOutcome::CONFIGURATION_ERROR], true)) {
            throw new PublicBuybackSubmissionException('Ehhez a készülékhez most nem küldhető be automatikus felvásárlási igény.');
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
        $amount = $selected->finalAmount?->amount();

        try {
            return $this->transactions->transactional(function () use ($tokenHash, $name, $email, $phone, $note, $modelKey, $device, $mode, $now, $timestamp, $canonicalAnswers, $publicSummary, $resolved, $results, $selected, $amount, $input): PublicBuybackSubmissionResult {
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
                    'selected_offer_mode' => $mode->code(),
                    'selected_final_amount_minor' => $amount,
                    'manual_review' => $selected->outcome->code() === PricingOutcome::MANUAL_REVIEW,
                    'privacy' => ['policy_url' => (string) ($input['privacy_url'] ?? ''), 'policy_marker' => (string) ($input['privacy_marker'] ?? ''), 'acknowledged' => true],
                ], $timestamp);
                return new PublicBuybackSubmissionResult($request->requestNumber()->value(), $device, $mode->code(), $amount, $selected->outcome->code() === PricingOutcome::MANUAL_REVIEW);
            });
        } catch (\Throwable $exception) {
            $existing = $this->store->findBySubmissionToken($tokenHash);
            if ($existing !== null) {
                return new PublicBuybackSubmissionResult((string) $existing['request_number'], (string) $existing['device_display_name'], (string) $existing['service_mode'], null, false, true);
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

    private function isUsablePhone(string $phone): bool
    {
        return preg_match('/^[+0-9 ()-]{7,64}$/', $phone) === 1 && preg_match_all('/[0-9]/', $phone) >= 7;
    }
}
