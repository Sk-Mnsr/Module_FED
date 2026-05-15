<?php

namespace App\Support;

use App\Models\CoficarteRecharge;
use App\Models\CoficarteSale;

final class CoficarteEncaissementRows
{
    /**
     * @return array<string, mixed>
     */
    public static function sale(CoficarteSale $s): array
    {
        $s->loadMissing(['user:id,name', 'card:id,numero_carte,agence_id,prix_vente', 'card.agence:id,nom']);

        $prixCarte = (int) ($s->card?->prix_vente ?? 0);
        $premiereRecharge = (int) ($s->montant_premiere_recharge ?? 0);

        return [
            'id' => $s->id,
            'numero_carte' => $s->card?->numero_carte ?? '—',
            'prix_vente' => $prixCarte,
            'montant_premiere_recharge' => $premiereRecharge,
            'montant_total_a_encaisser' => $prixCarte + $premiereRecharge,
            'vendeur' => $s->user?->name ?? '—',
            'acheteur' => $s->nom_client,
            'date_vente' => $s->date_vente?->format('d/m/Y'),
            'agence' => $s->card?->agence?->nom ?? '—',
            'type_acheteur' => $s->type_acheteur,
            'telephone_client' => $s->telephone_client,
            'email_client' => $s->email_client,
            'numero_compte_client' => $s->numero_compte_client,
            'compte_client_pack' => $s->compte_client_pack,
            'kyc_type_piece' => $s->kyc_type_piece,
            'kyc_numero_piece' => $s->kyc_numero_piece,
            'derniers_4' => $s->derniers_4,
            'created_at_detail' => $s->created_at?->format('d-m-Y H:i:s'),
            'numero_transaction' => '2'.str_pad((string) $s->id, 13, '0', STR_PAD_LEFT),
            'encaissement_code' => $s->encaissement_code,
            'date_encaisse_confirme' => $s->activated_at?->format('d-m-Y H:i:s'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function recharge(CoficarteRecharge $r): array
    {
        $r->loadMissing(['user:id,name', 'card:id,numero_carte,agence_id', 'card.agence:id,nom', 'agenceEnregistrement:id,nom']);

        $mnt = (int) $r->montant;
        $hon = (int) ($r->honoraire_chargement ?? 0);
        $numero = $r->card?->numero_carte ?? $r->numero_carte_saisi ?? '—';
        $agenceNom = $r->card?->agence?->nom ?? $r->agenceEnregistrement?->nom ?? '—';

        return [
            'id' => $r->id,
            'numero_carte' => $numero,
            'montant' => $mnt,
            'titulaire_carte' => $r->titulaire_carte,
            'email_titulaire' => $r->email_titulaire,
            'honoraire_chargement' => $hon,
            'montant_total_a_encaisser' => $mnt + $hon,
            'demandeur' => $r->user?->name ?? '—',
            'agence' => $agenceNom,
            'created_at' => $r->created_at?->format('d/m/Y H:i'),
            'commentaire' => $r->commentaire,
            'created_at_detail' => $r->created_at?->format('d-m-Y H:i:s'),
            'numero_transaction' => '3'.str_pad((string) $r->id, 13, '0', STR_PAD_LEFT),
            'encaissement_code' => $r->encaissement_code,
            'date_encaisse_confirme' => $r->confirmed_at?->format('d-m-Y H:i:s'),
        ];
    }
}
