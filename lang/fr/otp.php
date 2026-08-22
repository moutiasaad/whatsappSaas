<?php

return [
    'page_title' => 'Service OTP',
    'breadcrumb' => 'Service OTP',
    'title'      => 'Service OTP par WhatsApp',
    'subtitle'   => 'Envoyez des codes de vérification par WhatsApp depuis votre propre projet Laravel ou web/API.',

    'instance_connected'   => 'Envoi via :name',
    'no_connected_instance'=> 'Aucune instance WhatsApp connectée — connectez-en une pour activer l\'envoi.',

    'config_title'    => 'Configuration',
    'config_subtitle' => 'Contrôle la génération et l\'envoi des codes OTP.',
    'enable_label'    => 'Activer l\'API OTP',
    'enable_hint'     => 'Désactivé, /api/otp/send renvoie 403.',
    'code_length_label'=> 'Longueur du code',
    'digits'          => 'chiffres',
    'ttl_label'       => 'Validité (minutes)',
    'ttl_hint'        => 'Entre 1 et 60. Appliqué uniquement aux nouveaux codes.',
    'template_label'  => 'Modèle de message',
    'template_hint'   => 'Utilisez {code} pour le code et {ttl} pour la validité en minutes. {code} est obligatoire.',
    'save'            => 'Enregistrer',
    'saved'           => 'Paramètres OTP enregistrés.',
    'template_missing_code' => 'Le modèle de message doit contenir la variable {code}.',

    'credentials_title'   => 'Identifiants API',
    'credentials_subtitle'=> 'Utilisez-les pour authentifier les appels depuis votre autre projet.',
    'base_url_label'      => 'URL de base',
    'api_key_label'       => 'Valeur du header X-Api-Key',
    'api_key_hint'        => 'À traiter comme un mot de passe.',
    'regenerate_from_profile' => 'Régénérer depuis le profil',
    'no_api_key'          => 'Vous n\'avez pas encore de clé API.',
    'generate_api_key'    => 'Générer une clé depuis le profil.',

    'integration_title'   => 'Intégration',
    'integration_subtitle'=> 'Extraits prêts à coller pour appeler le service depuis votre autre projet.',
    'send_code'           => 'Envoyer un OTP',
    'verify_code'         => 'Vérifier un code',
    'laravel_hint'        => 'Nécessite guzzlehttp/guzzle. Stockez la clé API dans .env sous WAVADESK_API_KEY.',

    'response_ref_title'  => 'Référence de réponse',
    'response_ref_ok'     => 'true si succès, false sinon. Les erreurs incluent un message.',
    'response_ref_retry'  => 'Secondes à attendre avant de réessayer (uniquement en cooldown, HTTP 429).',
];
