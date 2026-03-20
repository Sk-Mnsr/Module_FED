<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="Content-Security-Policy" content="upgradeinsecure-requests">
        <title>{{ $data['subject'] }}</title>
    </head>
    <body style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sansserif; background-color: #f0f2f5;">
        <div style="max-width: 600px; margin: 40px auto; backgroundcolor: #ffffff; padding: 30px; border-radius: 10px;">
            <!-- Logo -->
        <div style="text-align: center; margin-bottom: 30px;">
            <img src="{{ $message->embed(public_path('logo.png'))}}" alt="Logo" style="max-width: 350px;">
        </div>
            <!-- Contenu -->
        <h1 style="color: #2c3e50;">{{ }}</h1>
        <p style="font-size: 16px; color: #333; line-height: 1.6;">
            {{ $data['content'] }}
        </p>
        <!-- Détails -->
        <div style="background-color: #f9fafc; border: 1px solid
        #e0e0e0; border-radius: 6px; padding: 20px; margin-top: 20px;">
        <h3 style="margin-top: 0; color: #2c3e50;">Détails</h3>
        <p>
            <strong>Référence :</strong> 
            {{ $data['reference'] }}
        </p>
        <p>
            <strong>Titre :</strong>
            {{ $data['title'] }}
        </p>
        <p>
            <strong>Créé le :</strong>
            {{ $data['created_at_fr']}}
        </p>
        </div>
        <!-- Footer -->
        <div style="margin-top: 30px; padding-top: 20px; border-top:
        1px solid #e0e0e0; text-align: center; color: #666; font-size:
        12px;">
        <p>© 2026 Cofina Sénégal. Tous droits réservés.</p>
        </div>
        </div>
    </body>
</html>

