<?php

return [
    'page_title' => 'Notification WhatsApp',
    'breadcrumb' => 'Notification WhatsApp',
    'title'      => 'Service de notification WhatsApp',
    'subtitle'   => 'Comme SMTP pour les e-mails — votre site envoie un message et l\'admin le reçoit sur WhatsApp.',

    'instance_connected'    => 'Envoi via :name',
    'no_connected_instance' => 'Aucune instance WhatsApp connectée — connectez-en une pour activer la livraison.',

    'config_title'    => 'Configuration',
    'config_subtitle' => 'Destinataire des notifications et présentation.',
    'enable_label'    => 'Activer l\'API Notify',
    'enable_hint'     => 'Désactivée, /api/notify/send renvoie 403.',

    'admin_phone_label'   => 'Numéro WhatsApp de l\'administrateur',
    'admin_phone_hint'    => 'Format E.164 avec indicatif pays (ex. +212600000000). Toutes les notifications y sont envoyées.',
    'admin_phone_invalid' => 'Saisissez un numéro valide au format international (7 à 15 chiffres, + optionnel).',

    'prefix_label' => 'Préfixe du message (facultatif)',
    'prefix_hint'  => 'Ajouté au début de chaque notification, ex. [Wavadesk]. Laissez vide pour aucun.',

    'save'  => 'Enregistrer',
    'saved' => 'Paramètres de notification enregistrés.',

    'credentials_title'    => 'Identifiants API',
    'credentials_subtitle' => 'À utiliser depuis votre site ou votre backend.',
    'endpoint_label'       => 'Point de terminaison',
    'api_key_label'        => 'Valeur de l\'en-tête X-Api-Key',
    'api_key_hint'         => 'À traiter comme un mot de passe.',
    'regenerate_from_profile' => 'Régénérer depuis le profil',
    'no_api_key'           => 'Vous n\'avez pas encore de clé API.',
    'generate_api_key'     => 'Générer depuis le profil.',

    'test_title'       => 'Envoyer un test',
    'test_subtitle'    => 'Envoyez immédiatement une notification à votre numéro admin.',
    'test_placeholder' => 'Corps du message…',
    'test_default'     => 'Notification de test depuis WavaDesk.',
    'test_button'      => 'Envoyer la notification de test',
    'test_sent'        => 'Notification de test envoyée. Vérifiez votre WhatsApp.',
    'test_failed'      => 'Échec de la notification de test. Vérifiez la configuration ci-dessus.',

    'integration_title'    => 'Intégration',
    'integration_subtitle' => 'À copier-coller dans votre site pour envoyer des notifications de commande/évènement.',
    'laravel_hint'         => 'Stockez la clé API dans .env sous WAVADESK_API_KEY.',

    'response_ref_title' => 'Référence de la réponse',
    'response_ref_ok'    => 'true en cas de succès, false sinon. Les erreurs incluent un message.',

    'recent_title'    => 'Notifications récentes',
    'recent_subtitle' => 'Les 20 derniers messages envoyés par ce service.',
    'col_sent_at'     => 'Envoyé le',
    'col_message'     => 'Message',
    'col_status'      => 'Statut',
];
