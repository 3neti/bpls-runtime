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
