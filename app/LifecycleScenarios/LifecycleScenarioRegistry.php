<?php

namespace App\LifecycleScenarios;

use InvalidArgumentException;

final class LifecycleScenarioRegistry
{
    /**
     * @return array<string, LifecycleScenarioDefinition>
     */
    public function all(): array
    {
        return [
            'manual_collection_receipt_visibility' => new LifecycleScenarioDefinition(
                key: 'manual_collection_receipt_visibility',
                label: 'Manual collection receipt visibility',
                mode: 'manual_collection_receipt_visibility',
                risk: 'local transactional',
                actors: [
                    'operator' => 'primary_operator',
                    'recipient' => 'sample_recipient',
                ],
                safety: [
                    'environments' => ['local', 'testing'],
                    'external_integrations' => false,
                    'irreversible_actions' => false,
                    'notifications' => false,
                ],
                expectations: [
                    'payment_schedule_status' => 'paid',
                    'collection_status' => 'receipted',
                    'receipt_status' => 'issued',
                    'numbering_authority' => 'manual',
                    'external_calls' => 0,
                    'irreversible_actions' => false,
                ],
                viewports: [
                    'desktop' => [
                        'width' => 1440,
                        'height' => 900,
                    ],
                    'mobile' => [
                        'width' => 390,
                        'height' => 844,
                    ],
                ],
            ),
            'permit_application_pending_payment_visibility' => new LifecycleScenarioDefinition(
                key: 'permit_application_pending_payment_visibility',
                label: 'Permit application pending payment visibility',
                mode: 'permit_application_pending_payment_visibility',
                risk: 'local transactional',
                actors: [
                    'operator' => 'primary_operator',
                    'recipient' => 'sample_recipient',
                ],
                safety: [
                    'environments' => ['local', 'testing'],
                    'external_integrations' => false,
                    'irreversible_actions' => false,
                    'notifications' => false,
                ],
                expectations: [
                    'canonical_state' => 'pending_payment',
                    'display_status' => 'pending_payment',
                    'payment_schedule_status' => 'pending',
                    'external_calls' => 0,
                    'can_continue' => true,
                ],
                viewports: [
                    'desktop' => [
                        'width' => 1440,
                        'height' => 900,
                    ],
                    'mobile' => [
                        'width' => 390,
                        'height' => 844,
                    ],
                ],
            ),
            'permit_application_cancelled_visibility' => new LifecycleScenarioDefinition(
                key: 'permit_application_cancelled_visibility',
                label: 'Permit application cancelled visibility',
                mode: 'permit_application_cancelled_visibility',
                risk: 'local transactional',
                actors: [
                    'operator' => 'primary_operator',
                    'recipient' => 'sample_recipient',
                ],
                safety: [
                    'environments' => ['local', 'testing'],
                    'external_integrations' => false,
                    'irreversible_actions' => false,
                    'notifications' => false,
                ],
                expectations: [
                    'canonical_state' => 'cancelled',
                    'display_status' => 'cancelled',
                    'is_terminal' => true,
                    'can_continue' => false,
                    'external_calls' => 0,
                ],
                viewports: [
                    'desktop' => [
                        'width' => 1440,
                        'height' => 900,
                    ],
                    'mobile' => [
                        'width' => 390,
                        'height' => 844,
                    ],
                ],
            ),
            'storyboard_terminal_state_visibility' => new LifecycleScenarioDefinition(
                key: 'storyboard_terminal_state_visibility',
                label: 'Storyboard terminal export visibility',
                mode: 'storyboard_terminal_state_visibility',
                risk: 'local transactional',
                actors: [
                    'operator' => 'primary_operator',
                    'recipient' => 'sample_recipient',
                ],
                safety: [
                    'environments' => ['local', 'testing'],
                    'external_integrations' => false,
                    'irreversible_actions' => false,
                    'notifications' => false,
                ],
                expectations: [
                    'canonical_state' => 'exported',
                    'display_status' => 'completed',
                    'pdf_exports' => 1,
                    'video_exports' => 1,
                    'external_calls' => 0,
                    'can_continue' => true,
                ],
                viewports: [
                    'desktop' => [
                        'width' => 1440,
                        'height' => 900,
                    ],
                    'mobile' => [
                        'width' => 390,
                        'height' => 844,
                    ],
                ],
            ),
        ];
    }

    public function get(string $key): LifecycleScenarioDefinition
    {
        return $this->all()[$key] ?? throw new InvalidArgumentException("Unknown lifecycle scenario [{$key}].");
    }
}
