<?php

namespace App\Support;

use App\Models\Project;
use App\Models\ProjectIntegration;

class IntegrationPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function forFrontend(ProjectIntegration $integration, Project $project): array
    {
        $widget = $integration->widget_slug
            ? F5WidgetCatalog::findBySlug($integration->widget_slug)
            : null;

        return [
            'id' => $integration->id,
            'name' => $integration->name,
            'comment' => $integration->comment,
            'billing_quantity' => $integration->billing_quantity,
            'payment_type' => $integration->widget_slug === null
                ? ($integration->payment_type ?: ProjectIntegration::PAYMENT_SUBSCRIPTION)
                : null,
            'cost' => $integration->cost !== null ? (float) $integration->cost : null,
            'widget_slug' => $integration->widget_slug,
            'widget_url' => F5WidgetCatalog::resolveUrl($integration->widget_slug, $integration->widget_url),
            'widget_category' => $widget['category'] ?? null,
            'is_custom' => $integration->widget_slug === null,
            'update_url' => route('projects.integrations.update', [$project, $integration]),
            'destroy_url' => route('projects.integrations.destroy', [$project, $integration]),
        ];
    }
}
