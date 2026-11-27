<?php

namespace App\Modules\Inbox\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Envío de avisos a la bandeja de entrada de los usuarios (E3.27).
 *
 * Se invoca dentro de la transacción del cambio que origina el aviso: si la
 * transacción se revierte, el aviso no queda. El mensaje no guarda texto sino
 * su tipo y sus parámetros, y la interfaz arma el texto en el idioma del
 * usuario, como los mensajes de la barra de estado. Los parámetros son una
 * foto del momento del aviso (el usuario y el rol asignados, quién asignó):
 * el texto no cambia si después cambian los datos.
 */
interface Inbox
{
    /**
     * @param  list<int>  $recipientIds  usuarios destinatarios; los repetidos reciben un solo aviso
     * @param  string  $type  tipo en la forma entidad.evento, por ejemplo user.critical_role_assigned
     * @param  array<string, scalar|null>  $parameters  datos con que la interfaz arma el texto
     * @param  Model|null  $subject  objeto al que se refiere el aviso, para abrirlo desde la bandeja
     */
    public function send(array $recipientIds, string $type, array $parameters = [], ?Model $subject = null): void;
}
