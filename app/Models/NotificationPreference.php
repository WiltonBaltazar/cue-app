<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-user choice of which notification types arrive as push (FR-025). In-app
 * notifications are always kept, whatever the preference (FR-040).
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property bool $push
 */
class NotificationPreference extends Model
{
    /**
     * Types a user can switch off as push, with who receives them (contracts/notifications.md).
     *
     * @var array<string, array{label: string, description: string, for: 'volunteer'|'coordinator'|'both'}>
     */
    public const TYPES = [
        'schedule_published' => ['label' => 'Escala publicada', 'description' => 'Quando sai uma escala com turnos seus.', 'for' => 'volunteer'],
        'assignment_changed' => ['label' => 'Alterações aos turnos', 'description' => 'Quando um turno seu é acrescentado ou retirado.', 'for' => 'volunteer'],
        'shift_check' => ['label' => 'Confirmação antes do turno', 'description' => 'Três dias antes, para dizer se ainda pode ir.', 'for' => 'volunteer'],
        'shift_reminder' => ['label' => 'Lembrete do turno', 'description' => 'Na véspera, com a hora de chegada.', 'for' => 'volunteer'],
        'event_cancelled' => ['label' => 'Eventos cancelados', 'description' => 'Quando um evento em que está escalado é cancelado.', 'for' => 'volunteer'],
        'swap_requested' => ['label' => 'Pedidos de troca', 'description' => 'Quando um colega procura quem o substitua.', 'for' => 'volunteer'],
        'swap_accepted' => ['label' => 'Trocas aceites', 'description' => 'Quando um colega aceita uma troca.', 'for' => 'both'],
        'swap_decided' => ['label' => 'Decisão sobre trocas', 'description' => 'Quando uma troca é aprovada ou recusada.', 'for' => 'volunteer'],
        'registration_decided' => ['label' => 'Decisão sobre a inscrição', 'description' => 'Aprovação, recusa ou pedido de correção.', 'for' => 'volunteer'],
        'registration_submitted' => ['label' => 'Novas inscrições', 'description' => 'Quando alguém se inscreve como voluntário.', 'for' => 'coordinator'],
        'replacement_needed' => ['label' => 'Turnos por substituir', 'description' => 'Quando uma ausência ou recusa deixa um lugar vazio.', 'for' => 'coordinator'],
        'data_removal_requested' => ['label' => 'Pedidos de remoção de dados', 'description' => 'Quando um voluntário pede para sair e apagar os seus dados.', 'for' => 'coordinator'],
        'availability_conflict' => ['label' => 'Conflitos de disponibilidade', 'description' => 'Quando a nova disponibilidade de alguém choca com turnos publicados.', 'for' => 'coordinator'],
    ];

    protected $fillable = ['user_id', 'type', 'push'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'push' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
