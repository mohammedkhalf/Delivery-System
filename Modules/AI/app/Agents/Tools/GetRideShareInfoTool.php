<?php

namespace Modules\AI\app\Agents\Tools;

use Modules\AI\app\Agents\AiResponseContext;
use App\Models\BusinessSetting;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Ride-share policy / safety / pricing-rules answers, sourced from
 * BusinessSetting where possible. Returns curated text per topic so the
 * customer doesn't get a wall of raw config.
 *
 * NEVER writes safety alerts — those go through the in-app safety button
 * (audit §8). This tool only points the customer at the right path.
 *
 * Allowed BusinessSetting keys (whitelisted to avoid leaking unrelated
 * config; mirrors GetPlatformInfoTool's pattern):
 *   - emergency_other_number    : JSON array of fallback emergency contacts
 *   - safety_feature_status     : 0/1 toggle for in-app safety button
 *   - emergency_call_status     : 0/1 toggle for one-tap emergency call
 *   - min_idle_fee_time         : buffer minutes before idle fees apply
 *   - min_delay_fee_time        : buffer minutes before delay fees apply
 *   - ride_commission           : admin commission % (NOT shown to customer)
 */
class GetRideShareInfoTool implements Tool
{
    private const ALLOWED_KEYS = [
        'emergency_other_number',
        'safety_feature_status',
        'emergency_call_status',
        'min_idle_fee_time',
        'min_delay_fee_time',
    ];

    private const VALID_TOPICS = ['safety', 'pricing', 'cancellation'];

    public function __construct(
        private readonly AiResponseContext $context,
    ) {}

    public function description(): string
    {
        return 'Answer ride-share policy and safety questions. Topics: "safety" (emergency contacts, how to report a problem), "pricing" (how fares are composed, what waiting/idle/surge means), "cancellation" (when fees apply). Pass null for a brief summary of all three.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'topic' => $schema->string()
                ->description('Which topic to answer: "safety", "pricing", or "cancellation". Pass null for a short summary of all three.')
                ->required()
                ->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $this->context->recordTool('GetRideShareInfoTool');

        $topic = $request->all()['topic'] ?? null;
        if ($topic !== null && ! in_array($topic, self::VALID_TOPICS, true)) {
            return 'Topic must be one of: ' . implode(', ', self::VALID_TOPICS) . ', or null for a summary.';
        }

        $settings = BusinessSetting::whereIn('key', self::ALLOWED_KEYS)
            ->pluck('value', 'key')
            ->all();

        $parts = [];
        if ($topic === 'safety' || $topic === null) {
            $parts[] = $this->safetyBlock($settings);
        }
        if ($topic === 'pricing' || $topic === null) {
            $parts[] = $this->pricingBlock($settings);
        }
        if ($topic === 'cancellation' || $topic === null) {
            $parts[] = $this->cancellationBlock();
        }

        return implode(PHP_EOL . PHP_EOL, array_filter($parts));
    }

    private function safetyBlock(array $settings): string
    {
        $safetyOn   = ($settings['safety_feature_status'] ?? '0') === '1';
        $callOn     = ($settings['emergency_call_status'] ?? '0') === '1';
        $rawNumbers = $settings['emergency_other_number'] ?? null;

        $numbers = [];
        if ($rawNumbers) {
            // Stored as JSON array per audit; tolerate scalar string too.
            $decoded = json_decode($rawNumbers, true);
            if (is_array($decoded)) {
                foreach ($decoded as $entry) {
                    $num = is_array($entry) ? ($entry['number'] ?? null) : $entry;
                    if (is_string($num) && trim($num) !== '') $numbers[] = trim($num);
                }
            } elseif (is_string($rawNumbers) && trim($rawNumbers) !== '') {
                $numbers[] = trim($rawNumbers);
            }
        }

        $lines = ['SAFETY:'];
        $lines[] = $safetyOn
            ? '• Tap the in-app safety button during a ride to report an unsafe situation. Drivers and admins are notified immediately.'
            : '• Safety reporting is handled through customer support.';
        if ($callOn && !empty($numbers)) {
            $lines[] = '• One-tap emergency call is available in the ride screen.';
        }
        if (!empty($numbers)) {
            $lines[] = '• Emergency contacts: ' . implode(', ', $numbers);
        }
        $lines[] = '• I can\'t file a safety report from chat — please use the app for that.';

        return implode(PHP_EOL, $lines);
    }

    private function pricingBlock(array $settings): string
    {
        $idleBuffer  = (int) ($settings['min_idle_fee_time']  ?? 0);
        $delayBuffer = (int) ($settings['min_delay_fee_time'] ?? 0);

        $lines = ['PRICING:'];
        $lines[] = '• Each ride = base fare + (distance × per-km rate). Use the fare estimate tool for actual numbers.';
        if ($idleBuffer > 0) {
            $lines[] = '• Idle time is free for the first ' . $idleBuffer . ' minutes; longer waits add an idle fee per minute.';
        } else {
            $lines[] = '• Idle time may add an idle fee per minute.';
        }
        if ($delayBuffer > 0) {
            $lines[] = '• If the actual trip runs more than ' . $delayBuffer . ' minutes over the estimate, a delay fee per minute applies.';
        } else {
            $lines[] = '• Trips that run long may include a delay fee per minute.';
        }
        $lines[] = '• Waiting fee, surge, and tax may also adjust the final fare.';

        return implode(PHP_EOL, $lines);
    }

    private function cancellationBlock(): string
    {
        // Cancellation fees are zone+category-specific and live in RideFare
        // rows (cancellation_fee_percent, min_cancellation_fee, penalty_fee_for_cancel).
        // We deliberately don't quote specific numbers here — they vary per
        // category — and steer the customer to the app for the exact amount.
        return 'CANCELLATION:' . PHP_EOL
            . '• You can cancel from the ride request screen before the driver arrives.' . PHP_EOL
            . '• A cancellation fee may apply once a driver is en route; the amount depends on the vehicle category and how close the driver was.' . PHP_EOL
            . '• The exact fee for a specific ride is shown in the app at the moment of cancellation.';
    }
}
