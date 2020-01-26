<?php
// This software is copyright 2020 Ryan Nutt - https://www.nutt.net
//
// This software is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This software is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// See http://www.gnu.org/licenses/ for more information

namespace Aelora\WordPress;

if (!class_exists(__NAMESPACE__ . '\PostTransient')) {
    class PostTransient
    {

        public static function get($post_id, $key, $default = '')
        {
            $transient = get_post_meta($post_id, '_transient_' . $key, true);

            if (empty($transient['expiration'])) {
                return $default;
            } else if ($transient['expiration'] < time()) {
                self::delete($post_id, $key);
                return $default;
            }
            return $transient['value'];
        }

        public static function set($post_id, $key, $value, $expiration = 24 * HOUR_IN_SECONDS)
        {
            update_post_meta($post_id, '_transient_' . $key, [
                'expiration' => time() + $expiration,
                'value' => $value
            ]);
        }

        public static function delete($post_id, $key)
        {
            delete_post_meta($post_id, '_transient_' . $key);
        }
    }
}
