<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'message',
        'action_url',
        'action_label',
        'read_at',
    ];

    public function tieneAccion(): bool
    {
        return filled($this->action_url) && filled($this->action_label) && ! $this->estaPagada();
    }

    public function estaPagada(): bool
    {
        return $this->action_label === 'Pagado';
    }

    /**
     * Marca notificaciones de pago (Pagar) como Pagado y leídas.
     * Busca por fragmentos de URL (ej. abono_ids=12 o pago_manual_id=5).
     *
     * @param  list<string>  $urlFragments
     */
    public static function marcarPagadasPorUrl(array $urlFragments): int
    {
        $fragments = array_values(array_filter(array_map('strval', $urlFragments)));
        if ($fragments === []) {
            return 0;
        }

        $query = static::query()
            ->where('action_label', 'Pagar')
            ->whereNotNull('action_url')
            ->where(function ($q) use ($fragments) {
                foreach ($fragments as $fragment) {
                    $q->orWhere('action_url', 'like', '%'.$fragment.'%');
                }
            });

        return $query->update([
            'action_label' => 'Pagado',
            'action_url' => null,
            'read_at' => now(),
        ]);
    }

    /**
     * Sincroniza avisos "Pagar" cuyo adelanto ya fue exportado/pagado.
     */
    public static function sincronizarAdelantosPagados(?int $receiverId = null): int
    {
        $avisos = static::query()
            ->where('action_label', 'Pagar')
            ->whereNotNull('action_url')
            ->where('action_url', 'like', '%abono_ids=%')
            ->when($receiverId, fn ($q) => $q->where('receiver_id', $receiverId))
            ->get(['id', 'action_url']);

        if ($avisos->isEmpty()) {
            return 0;
        }

        $idsPorAviso = [];
        $todos = [];
        foreach ($avisos as $aviso) {
            if (! preg_match_all('/abono_ids=([0-9,\s]+)/', (string) $aviso->action_url, $m)) {
                continue;
            }
            $ids = [];
            foreach ($m[1] as $chunk) {
                foreach (preg_split('/[,\s]+/', $chunk) as $raw) {
                    $id = (int) $raw;
                    if ($id > 0) {
                        $ids[] = $id;
                        $todos[] = $id;
                    }
                }
            }
            if ($ids !== []) {
                $idsPorAviso[$aviso->id] = array_values(array_unique($ids));
            }
        }

        if ($todos === []) {
            return 0;
        }

        $pagados = \App\Models\Nomina\NominaAbonoSueldo::query()
            ->whereIn('id', array_values(array_unique($todos)))
            ->whereNotNull('txt_exportado_at')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $pagados = array_fill_keys($pagados, true);

        $marcar = [];
        foreach ($idsPorAviso as $avisoId => $ids) {
            foreach ($ids as $id) {
                if (isset($pagados[$id])) {
                    $marcar[] = $avisoId;
                    break;
                }
            }
        }

        if ($marcar === []) {
            return 0;
        }

        return static::query()
            ->whereIn('id', $marcar)
            ->update([
                'action_label' => 'Pagado',
                'action_url' => null,
                'read_at' => now(),
            ]);
    }

    protected $casts = [
        'read_at' => 'datetime',
    ];

    /**
     * Get the user who sent the notification.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Get the user who receives the notification.
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    /**
     * Scope to only get unread notifications.
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }
}
