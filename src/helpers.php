<?php

if (! function_exists('class_uses_trait')) {
    /**
     * Checks if trait is used by a target class.
     * It recurses through the whole class inheritance tree.
     *
     * @param  mixed  $trait_class
     * @param  mixed  $target_class
     */
    function class_uses_trait($trait_class, $target_class): bool
    {
        return in_array($trait_class, class_uses_recursive($target_class));
    }
}
