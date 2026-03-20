<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="Content-Security-Policy" content="upgradeinsecure-requests">
        <title>{{ $data['subject'] }}</title>
    </head>
    <body style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f0f2f5; margin: 0; padding: 20px;">
        <div style="max-width: 600px; margin: 40px auto; background-color: #ffffff; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            
            <!-- Logo -->
            <div style="text-align: center; margin-bottom: 30px;">
                <img src="{{ $message->embed(public_path('logo.png')) }}" alt="Logo" style="max-width: 350px;">
            </div>
            
            <!-- En-tête de rejet -->
            <div style="background-color: #f39c12; color: white; padding: 15px; border-radius: 6px; margin-bottom: 20px; text-align: center;">
                <h1 style="margin: 0; font-size: 24px;">❌ Rejet</h1>
            </div>
            
            <!-- Contenu principal -->
            <div style="margin-bottom: 25px;">
                <h2 style="color: #2c3e50; margin-bottom: 15px;">{{ $data['title'] ?? 'Demande rejetée' }}</h2>
                <p style="font-size: 16px; color: #333; line-height: 1.6; margin-bottom: 20px;">
                    {{ $data['content'] }}
                </p>
                
                <!-- Motif du rejet -->
                @isset($data['rejection_reason'])
                    <div style="background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 15px; margin-top: 20px;">
                        <h4 style="margin-top: 0; color: #856404;">Motif du rejet</h4>
                        <p style="margin-bottom: 0; color: #856404;">
                            {{ $data['rejection_reason'] }}
                        </p>
                    </div>
                @endisset
            </div>
            
            <!-- Détails -->
            @isset($data['details'])
            <div style="background-color: #f9fafc; border: 1px solid #e0e0e0; border-radius: 6px; padding: 20px; margin-top: 20px;">
                <h3 style="margin-top: 0; color: #2c3e50;">Détails de la demande</h3>
                @foreach($data['details'] as $label => $value)
                    <p style="margin-bottom: 8px;">
                        <strong>{{ $label }} :</strong> {{ $value }}
                    </p>
                @endforeach
            </div>
            @endisset
            
            <!-- Actions possibles -->
            @isset($data['actions_available'])
            <div style="background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 20px; margin-top: 20px;">
                <h3 style="margin-top: 0; color: #2c3e50;">Actions possibles</h3>
                @foreach($data['actions_available'] as $action)
                    <p style="margin-bottom: 10px;">
                        @isset($action['url'])
                            <a href="{{ $action['url'] }}" 
                               style="color: #007bff; text-decoration: none; font-weight: bold;">
                                {{ $action['text'] }}
                            </a>
                        @else
                            <span style="color: #666;">• {{ $action['text'] }}</span>
                        @endisset
                    </p>
                @endforeach
            </div>
            @endisset
            
            <!-- Bouton de modification -->
            @isset($data['modify_url'])
            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ $data['modify_url'] }}" 
                   style="background-color: #f39c12; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;">
                    {{ $data['modify_text'] ?? 'Modifier la demande' }}
                </a>
            </div>
            @endisset
            
            <!-- Footer -->
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0; text-align: center; color: #666; font-size: 12px;">
                <p>© 2026 Cofina Sénégal. Tous droits réservés.</p>
                <p style="margin-top: 10px; font-style: italic;">Pour toute question, veuillez contacter le support technique.</p>
            </div>
        </div>
    </body>
</html>
