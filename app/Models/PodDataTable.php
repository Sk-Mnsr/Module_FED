<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PodDataTable extends Model
{
    protected $table = 'pod_data_tables';

    protected $fillable = [
        'code',
        'libelle',
        'description',
        'actif',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }

    public function columns(): HasMany
    {
        return $this->hasMany(PodDataTableColumn::class, 'pod_data_table_id')->orderBy('sort_order');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(PodDataTableRow::class, 'pod_data_table_id')->orderBy('sort_order');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * Options pour un champ de type Table (colonne valeur / libellé).
     *
     * @param  list<string>  $colonnesDesactivees
     * @return list<array{code: string, libelle: string, actif: bool, ordre: int}>
     */
    public function optionsFor(string $colonneValeur, string $colonneLibelle, array $colonnesDesactivees = []): array
    {
        $colonneValeur = strtoupper(trim($colonneValeur));
        $colonneLibelle = strtoupper(trim($colonneLibelle));
        $disabled = array_map(
            static fn ($c) => strtoupper(trim((string) $c)),
            $colonnesDesactivees
        );

        if ($colonneValeur === '' || $colonneLibelle === '') {
            return [];
        }

        if (in_array($colonneValeur, $disabled, true) || in_array($colonneLibelle, $disabled, true)) {
            return [];
        }

        $out = [];
        $seen = [];

        foreach ($this->rows()->where('actif', true)->orderBy('sort_order')->cursor() as $row) {
            $data = is_array($row->data) ? $row->data : [];
            $code = trim((string) ($data[$colonneValeur] ?? ''));
            if ($code === '' || isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;
            $libelle = trim((string) ($data[$colonneLibelle] ?? $code));
            $out[] = [
                'code' => $code,
                'libelle' => $libelle !== '' ? $libelle : $code,
                'actif' => true,
                'ordre' => (int) $row->sort_order,
            ];
        }

        return $out;
    }
}
