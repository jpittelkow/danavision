<?php

/**
 * User settings schema: defines allowed groups for per-user settings.
 *
 * Groups listed here are the only groups a user can write to via the
 * generic Settings API. Keys within each group use a wildcard ('*')
 * approach — any key is accepted as long as the group is valid.
 *
 * Note: The UserSettingController (/user/settings) has its own explicit
 * field-level validation and does not use this schema.
 */
return [
    'general',
    'appearance',
    'notifications',
    'defaults',
];
