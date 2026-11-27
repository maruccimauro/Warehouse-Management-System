<?php

return [
    'invalid_credentials' => 'The provided credentials are invalid.',
    'session_required' => 'Authentication requires a frontend session.',
    'warehouse_not_assigned' => 'The warehouse is not assigned to the user: it can be viewed, but not operated on.',
    'permission_denied' => 'The user is not allowed to perform this action.',
    'user_inactive' => 'The user is deactivated. Contact the system administrator.',
    'password_change_required' => 'The password must be changed before continuing.',
    'last_administrator' => 'The change would leave the system without an active administrator.',
    'critical_role_confirmation_required' => 'Assigning a critical role requires confirming it.',
    'self_deactivation_not_allowed' => 'Users cannot deactivate themselves.',
    'own_roles_not_editable' => 'Users cannot change their own roles.',
    'role_exceeds_permissions' => 'Only roles whose permissions the acting user holds can be assigned or removed.',
    'target_exceeds_permissions' => 'The user holds permissions that the acting user does not.',
];
