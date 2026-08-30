<?php

declare(strict_types=1);

namespace SenNoKuni\Tests\Characterization;

use PHPUnit\Framework\TestCase;
use SenNoKuni\Point\OrlyReferralPointAwardPlanner;

final class OrlyReferralPointAwardPlannerTest extends TestCase
{
    public function testRegistrantReceivesSignupAwardWithoutReferrer(): void
    {
        $planner = new OrlyReferralPointAwardPlanner();

        $awards = $planner->plan($this->baseContext());

        self::assertCount(1, $awards);
        self::assertSame('registrant', $awards[0]['recipient_type']);
        self::assertSame('cu_new', $awards[0]['recipient_common_user_id']);
        self::assertSame(3000, $awards[0]['points']);
        self::assertSame('orly', $awards[0]['currency_code']);
        self::assertSame('pending', $awards[0]['wallet_delivery_status']);
    }

    public function testAdvisorReferralAwardsRegistrantDirectReferrerAndUpperDirector(): void
    {
        $planner = new OrlyReferralPointAwardPlanner();

        $awards = $planner->plan($this->baseContext([
            'direct_referrer_agent' => [
                'id' => 30,
                'code' => 'adv001',
                'common_user_id' => 'cu_referrer',
                'position_type' => 'advisor',
                'status' => 'active',
            ],
            'ancestor_agents' => [
                [
                    'id' => 20,
                    'code' => 'dir001',
                    'common_user_id' => 'cu_director',
                    'position_type' => 'director',
                    'status' => 'active',
                ],
                [
                    'id' => 10,
                    'code' => 'agent001',
                    'position_type' => 'agent',
                    'status' => 'active',
                ],
            ],
        ]));

        self::assertSame(['registrant', 'direct_referrer', 'upper_director'], array_column($awards, 'recipient_type'));
        self::assertSame([3000, 3000, 1000], array_column($awards, 'points'));
        self::assertSame('adv001', $awards[1]['recipient_agent_code']);
        self::assertSame('dir001', $awards[2]['recipient_agent_code']);
    }

    public function testAgentCandidateDirectReferrerIsAwardedLikeAgentWithoutUpperDirector(): void
    {
        $planner = new OrlyReferralPointAwardPlanner();

        $awards = $planner->plan($this->baseContext([
            'direct_referrer_agent' => [
                'id' => 40,
                'code' => 'candidate001',
                'common_user_id' => 'cu_candidate',
                'position_type' => 'agent_candidate',
                'status' => 'active',
            ],
            'ancestor_agents' => [
                [
                    'id' => 10,
                    'code' => 'agent001',
                    'position_type' => 'agent',
                    'status' => 'active',
                ],
            ],
        ]));

        self::assertSame(['registrant', 'direct_referrer'], array_column($awards, 'recipient_type'));
        self::assertSame('candidate001', $awards[1]['recipient_agent_code']);
    }

    public function testAwardEventKeyIsDeterministicAndContainsNoPersonalData(): void
    {
        $planner = new OrlyReferralPointAwardPlanner();
        $context = $this->baseContext([
            'trigger_event_id' => 'event-001',
            'direct_referrer_agent' => [
                'id' => 30,
                'code' => 'adv001',
                'common_user_id' => 'cu_referrer',
                'position_type' => 'advisor',
            ],
        ]);

        $first = $planner->plan($context);
        $second = $planner->plan($context);

        self::assertSame($first[1]['award_event_key'], $second[1]['award_event_key']);
        self::assertStringStartsWith('orly_', $first[1]['award_event_key']);
        self::assertStringNotContainsString('adv001', $first[1]['award_event_key']);
        self::assertStringNotContainsString('cu_referrer', $first[1]['award_event_key']);
    }

    public function testSeminarAttendanceAwardsAttendeeReferrerAndUpperDirector(): void
    {
        $planner = new OrlyReferralPointAwardPlanner();

        $awards = $planner->plan($this->baseContext([
            'campaign' => [
                'id' => 2,
                'campaign_key' => 'orly_seminar_attendance',
                'currency_code' => 'orly',
            ],
            'campaign_version' => [
                'id' => 2,
                'version_no' => 1,
                'registrant_points' => 10000,
                'direct_referrer_points' => 11000,
                'upper_director_points' => 11000,
            ],
            'target_recipient_type' => 'attendee',
            'trigger_event_type' => 'seminar.attended',
            'trigger_event_id' => 'seminar_attendance:abc123',
            'direct_referrer_agent' => [
                'id' => 30,
                'code' => 'adv001',
                'common_user_id' => 'cu_referrer',
                'position_type' => 'advisor',
                'status' => 'active',
            ],
            'ancestor_agents' => [
                [
                    'id' => 20,
                    'code' => 'dir001',
                    'common_user_id' => 'cu_director',
                    'position_type' => 'director',
                    'status' => 'active',
                ],
            ],
        ]));

        self::assertSame(['attendee', 'direct_referrer', 'upper_director'], array_column($awards, 'recipient_type'));
        self::assertSame([10000, 11000, 11000], array_column($awards, 'points'));
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function baseContext(array $overrides = []): array
    {
        return array_replace_recursive([
            'campaign' => [
                'id' => 1,
                'campaign_key' => 'orly_referral_signup',
                'currency_code' => 'orly',
            ],
            'campaign_version' => [
                'id' => 1,
                'version_no' => 1,
                'registrant_points' => 3000,
                'direct_referrer_points' => 3000,
                'upper_director_points' => 1000,
            ],
            'trigger_event_type' => 'referral.confirmed',
            'trigger_event_id' => 'event-123',
            'target_common_user_id' => 'cu_new',
            'source_system_key' => 'sengoku-passport',
            'project_key' => 'orly',
        ], $overrides);
    }
}
