<?php

namespace App\Shared\Exceptions;

/**
 * Quien creó un documento no decide su aprobación (segregación de funciones:
 * INV-010, REG-001, REG-024). Vale aunque el usuario tenga el permiso de
 * aprobar y sea Administrador: el permiso habilita la acción, no la decisión
 * sobre lo propio. Es compartida porque la aplican varios documentos (ajuste
 * de stock en E3, orden de compra en E4, inventario físico).
 */
final class SelfApprovalNotAllowed extends DomainException
{
    public function __construct()
    {
        parent::__construct(detail: self::text('problems.self_approval_not_allowed.detail'));
    }

    public function errorCode(): string
    {
        return 'SELF_APPROVAL_NOT_ALLOWED';
    }

    public function title(): string
    {
        return self::text('problems.self_approval_not_allowed.title');
    }

    public function status(): int
    {
        return 403;
    }

    private static function text(string $key): string
    {
        $text = __($key);

        return is_string($text) ? $text : $key;
    }
}
