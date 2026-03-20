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
            
            <!-- En-tête d'alerte -->
            <div style="background-color: #e74c3c; color: white; padding: 15px; border-radius: 6px; margin-bottom: 20px; text-align: center;">
                <h1 style="margin: 0; font-size: 24px;">🚨 Alerte</h1>
            </div>
            
            <!-- Contenu principal -->
            <div style="margin-bottom: 25px;">
                <h2 style="color: #2c3e50; margin-bottom: 15px;">{{ $data['title'] ?? 'Alerte importante' }}</h2>
                <p style="font-size: 16px; color: #333; line-height: 1.6; margin-bottom: 20px;">
                    {{ $data['content'] }}
                </p>
                
                <!-- Niveau d'alerte -->
                @isset($data['alert_level'])
                    <div style="background-color: #fee; border: 1px solid #fcc; border-radius: 6px; padding: 15px; margin-top: 20px;">
                        <p style="margin: 0; color: #c00; font-weight: bold;">
                            📍 Niveau d'alerte : {{ $data['alert_level'] }}
                        </p>
                    </div>
                @endisset
                
                <!-- Action requise -->
                @isset($data['action_required'])
                    <div style="background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 15px; margin-top: 20px;">
                        <p style="margin: 0; color: #856404; font-weight: bold;">
                            ⚠️ Action requise : {{ $data['action_required'] }}
                        </p>
                    </div>
                @endisset
            </div>
            
            <!-- Détails -->
            @isset($data['details'])
            <div style="background-color: #f9fafc; border: 1px solid #e0e0e0; border-radius: 6px; padding: 20px; margin-top: 20px;">
                <h3 style="margin-top: 0; color: #2c3e50;">Détails de l'alerte</h3>
                @foreach($data['details'] as $label => $value)
                    <p style="margin-bottom: 8px;">
                        <strong>{{ $label }} :</strong> {{ $value }}
                    </p>
                @endforeach
            </div>
            @endisset
            
            <!-- Bouton d'action urgent -->
            @isset($data['action_url'])
            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ $data['action_url'] }}" 
                   style="background-color: #e74c3c; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;">
                    {{ $data['action_text'] ?? 'Traiter l\'alerte' }}
                </a>
            </div>
            @endisset
            
            <!-- Footer -->
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0; text-align: center; color: #666; font-size: 12px;">
                <p>© 2026 Cofina Sénégal. Tous droits réservés.</p>
                <p style="margin-top: 10px; font-style: italic;">Cet email contient une alerte importante. Merci d'en prendre connaissance.</p>
            </div>
        </div>
    </body>
</html>
