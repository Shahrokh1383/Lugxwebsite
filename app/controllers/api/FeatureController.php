<?php

namespace App\Controllers\Api;

use App\Core\Controller;

/**
 * Feature API Controller
 * Handles requests for website features displayed on the homepage.
 */
class FeatureController extends Controller
{
    public function index(): void
    {
        // For now, return static feature data.
        // In a real application, this data might come from a database table (e.g., 'features').
        $features = [
            [
                'id' => 1,
                'title' => 'Cloud Saves',
                'description' => 'Never lose your progress with free cloud storage',
                'icon_url' => '../assets/img/featured-01.png',
                'link' => '#' // Optional: link to a page explaining the feature
            ],
            [
                'id' => 2,
                'title' => 'Multiplayer',
                'description' => 'Connect with friends in online multiplayer games',
                'icon_url' => '../assets/img/featured-02.png',
                'link' => '#'
            ],
            [
                'id' => 3,
                'title' => '24/7 Support',
                'description' => 'Our gaming experts are always ready to help',
                'icon_url' => '../assets/img/featured-03.png',
                'link' => '#'
            ],
            [
                'id' => 4,
                'title' => 'Easy Refunds',
                'description' => '30-day money back guarantee on all purchases',
                'icon_url' => '../assets/img/featured-04.png',
                'link' => '#'
            ],
        ];

        $this->renderApiJson([
            'status' => 'success',
            'total' => count($features),
            'data' => $features
        ]);
    }
}
