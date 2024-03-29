<?php

/**
 * Hook provider interface.
 *
 * @package   Cedaro\WP\Plugin
 * @copyright Copyright (c) 2015 Cedaro, LLC
 * @license   MIT
 */
namespace Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin;

/**
 * Hook provider interface.
 *
 * @package Cedaro\WP\Plugin
 */
interface HookProviderInterface
{
    /**
     * Registers hooks for the plugin.
     */
    public function register_hooks();
}
