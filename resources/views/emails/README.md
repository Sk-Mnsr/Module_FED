# Templates d'Emails de Notification

Ce dossier contient les différents templates d'emails pour le système de notifications du module FED.

## Structure des Templates

### 1. Notification Standard (`notification/notification.blade.php`)
Template de base pour les notifications générales.

**Données attendues :**
```php
$data = [
    'subject' => 'Sujet de l\'email',
    'title' => 'Titre de la notification',
    'content' => 'Contenu principal',
    'action_required' => 'Action requise (optionnel)',
    'details' => [
        'Référence' => 'REF-001',
        'Date' => '10/03/2026',
        // ... autres détails
    ],
    'action_url' => 'https://example.com/action',
    'action_text' => 'Texte du bouton',
    'footer_note' => 'Note supplémentaire (optionnel)'
];
```

### 2. Alerte (`alerte/alerte.blade.php`)
Template pour les alertes urgentes avec design rouge.

**Données attendues :**
```php
$data = [
    'subject' => 'Alerte Critique',
    'title' => 'Titre de l\'alerte',
    'content' => 'Description de l\'alerte',
    'alert_level' => 'Critique/Elevée/Moyenne',
    'action_required' => 'Action immédiate requise',
    'details' => [
        'Source' => 'Système X',
        'Impact' => 'Élevé',
        // ... autres détails
    ],
    'action_url' => 'https://example.com/urgent-action',
    'action_text' => 'Traiter l\'alerte'
];
```

### 3. Validation (`valide/validation.blade.php`)
Template pour les confirmations et validations avec design vert.

**Données attendues :**
```php
$data = [
    'subject' => 'Validation Réussie',
    'title' => 'Opération validée',
    'content' => 'Votre demande a été validée',
    'success_message' => 'Message de succès personnalisé',
    'details' => [
        'Référence' => 'VAL-001',
        'Validé par' => 'Admin',
        'Date de validation' => '10/03/2026',
        // ... autres détails
    ],
    'validation_info' => [
        'Info 1',
        'Info 2',
        // ... autres informations
    ],
    'action_url' => 'https://example.com/view-details',
    'action_text' => 'Voir les détails'
];
```

### 4. Rejet (`rejet/rejet.blade.php`)
Template pour les rejets de demandes avec design orange.

**Données attendues :**
```php
$data = [
    'subject' => 'Demande Rejetée',
    'title' => 'Votre demande a été rejetée',
    'content' => 'Malheureusement, votre demande n\'a pas été approuvée',
    'rejection_reason' => 'Motif détaillé du rejet',
    'details' => [
        'Référence' => 'REJ-001',
        'Date de soumission' => '09/03/2026',
        'Statut' => 'Rejeté',
        // ... autres détails
    ],
    'actions_available' => [
        [
            'text' => 'Modifier la demande',
            'url' => 'https://example.com/modify'
        ],
        [
            'text' => 'Contacter le support',
            'url' => 'https://example.com/support'
        ]
    ],
    'modify_url' => 'https://example.com/modify',
    'modify_text' => 'Modifier la demande'
];
```

## Utilisation dans Laravel

### Exemple d'envoi d'email :
```php
use Illuminate\Support\Facades\Mail;

// Notification standard
Mail::send('emails.notification.notification', ['data' => $data], function ($message) use ($user) {
    $message->to($user->email)
            ->subject($data['subject']);
});

// Alerte
Mail::send('emails.alerte.alerte', ['data' => $data], function ($message) use ($user) {
    $message->to($user->email)
            ->subject($data['subject']);
});

// Validation
Mail::send('emails.valide.validation', ['data' => $data], function ($message) use ($user) {
    $message->to($user->email)
            ->subject($data['subject']);
});

// Rejet
Mail::send('emails.rejet.rejet', ['data' => $data], function ($message) use ($user) {
    $message->to($user->email)
            ->subject($data['subject']);
});
```

## Points Importants

1. **Logo** : Tous les templates utilisent `{{ $message->embed(public_path('logo.png')) }}` pour intégrer le logo
2. **Responsive** : Les templates sont optimisés pour les mobiles avec un max-width de 600px
3. **Sécurité** : Inclusion de la politique de sécurité de contenu
4. **Personnalisation** : Tous les champs sont optionnels sauf `subject` et `content`
5. **Branding** : Couleurs cohérentes avec l'identité Cofina Sénégal

## Champs Communs

- `subject` : Sujet de l'email (obligatoire)
- `title` : Titre principal
- `content` : Contenu principal (obligatoire)
- `details` : Tableau associatif pour les détails supplémentaires
- `action_url` : URL pour le bouton d'action
- `action_text` : Texte du bouton d'action
- `footer_note` : Note optionnelle dans le footer
