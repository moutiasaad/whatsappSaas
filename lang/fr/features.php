<?php

/*
 * French copy for the /features/<slug> pages.
 *
 * Shape must mirror lang/en/features.php exactly: config/seo_pages.php drives
 * the loops, so each 'toc' needs the same number of labels as that page has
 * sections, and each 'faq' entry the same number of answer paragraphs. A short
 * array here silently renders a raw translation key on the page.
 */

return [
    'breadcrumb_home' => 'Accueil',
    'breadcrumb_features' => 'Fonctionnalités',
    'on_this_page' => 'Sur cette page',
    'faq_title' => 'Questions fréquentes',
    'related_title' => 'À lire ensuite',
    'related_sub' => 'Les autres parties de la plateforme qui fonctionnent avec :topic.',
    'see_pricing' => 'Voir les tarifs',
    'micro_trial' => 'Essai gratuit de :days jours',
    'micro_nocard' => 'Sans carte à l’inscription',
    'micro_from' => 'À partir de :price par mois',
    'cta_title' => 'Structurez enfin votre support WhatsApp',
    'cta_sub' => 'Une boîte de réception partagée, un verrouillage qui empêche les réponses en double, et une IA qui répond à partir de votre propre base de connaissances.',
    'index_title' => 'Fonctionnalités | wavadesk',
    'index_description' => 'Tout ce que fait wavadesk : une boîte de réception WhatsApp partagée avec verrouillage, une IA ancrée dans votre base de connaissances, le chat en direct, les équipes et le routage, les OTP, les réservations, les rapports et une API.',
    'index_h1' => 'Tout ce que fait wavadesk',
    'index_lede' => 'Une boîte de réception partagée pour WhatsApp et le chat de votre site, une IA qui répond à partir de votre propre documentation, et les modules qui viennent s’y greffer.',
    'whatsapp-shared-inbox' => [
        'title' => 'Boîte de réception WhatsApp partagée pour équipes support | wavadesk',
        'description' => 'Transformez un seul numéro WhatsApp Business en boîte de réception d’équipe. Les agents prennent les conversations en charge, le verrouillage est appliqué en base de données, et chaque action est journalisée.',
        'h1' => 'Une boîte WhatsApp partagée que <em>toute votre équipe</em> peut traiter',
        'lede' => 'La plupart des équipes support commencent par se passer un téléphone. wavadesk transforme ce numéro en véritable file d’attente : les conversations arrivent dans un pool partagé, un agent en prend une en charge, et personne ne répond deux fois au même client.',
        'kicker' => '',
        'nav_title' => 'Boîte WhatsApp partagée',
        'nav_sub' => 'Une file, un verrouillage, zéro réponse en double',
        'toc' => [
            0 => 'Le problème du téléphone unique',
            1 => 'Comment fonctionne la boîte partagée',
            2 => 'Le verrouillage',
            3 => 'Historique client',
            4 => 'Journal d’audit',
            5 => 'Se connecter',
            6 => 'À qui cela s’adresse',
            7 => 'FAQ',
        ],
        'faq' => [
            0 => [
                'q' => 'Ai-je besoin d’un nouveau numéro de téléphone ?',
                'a' => [
                    0 => 'Non. Vous connectez le numéro WhatsApp Business que vous utilisez déjà en scannant un QR code, exactement comme pour WhatsApp Web. Il n’y a ni migration ni portabilité.',
                    1 => 'Vos clients continuent d’écrire au même numéro : rien ne change de leur côté.',
                ],
            ],
            1 => [
                'q' => 'Deux agents peuvent-ils répondre au même client par accident ?',
                'a' => [
                    0 => 'Non. Prendre une conversation en charge écrit un verrou, et un second agent ne peut ni la reprendre ni y écrire. Le verrou est appliqué en base de données et pas seulement dans l’interface : il tient même si plusieurs personnes cliquent en même temps.',
                ],
            ],
            2 => [
                'q' => 'Que devient notre historique quand un agent quitte l’équipe ?',
                'a' => [
                    0 => 'Il reste dans l’espace de travail. L’historique des conversations, les notes et les étiquettes appartiennent à l’entreprise, pas à un appareil ni à un compte personnel : retirer un agent n’efface pas la trace de ce qu’il a traité.',
                ],
            ],
            3 => [
                'q' => 'Est-ce compatible avec l’application WhatsApp sur mon téléphone ?',
                'a' => [
                    0 => 'Nous recommandons de faire passer le numéro par wavadesk pour que la file reste la seule source de vérité. Répondre directement depuis le combiné contourne la prise en charge et le journal d’audit, c’est-à-dire exactement ce qu’une boîte partagée sert à supprimer.',
                ],
            ],
            4 => [
                'q' => 'Y a-t-il une limite de conversations ?',
                'a' => [
                    0 => 'Non. Toutes les offres incluent les conversations WhatsApp en illimité, sans frais par message ni frais de conversation Meta. Les offres diffèrent sur le nombre de sièges, le quota de messages IA et les modules activés.',
                ],
            ],
            5 => [
                'q' => 'Puis-je voir combien de temps les clients attendent ?',
                'a' => [
                    0 => 'Oui. Le tableau de bord donne la médiane de première réponse, le temps de résolution, le volume par canal et la charge par agent ; la file met en avant la conversation non prise en charge la plus ancienne pour que rien ne vieillisse en silence.',
                ],
            ],
        ],
    ],
    'whatsapp-multi-agent' => [
        'title' => 'Plusieurs agents sur un seul numéro WhatsApp | wavadesk',
        'description' => 'Comment permettre à toute une équipe support de répondre depuis un seul numéro WhatsApp Business, avec la propriété des conversations, le routage et zéro réponse en double.',
        'h1' => 'Faites répondre <em>plusieurs agents</em> sur un seul numéro WhatsApp',
        'lede' => 'WhatsApp Business a été conçu pour une personne tenant un téléphone. Une équipe support n’est pas une personne. Voici comment fonctionne réellement l’accès multi-agents, ce qui casse sans lui, et ce à quoi il faut faire attention.',
        'kicker' => '',
        'nav_title' => 'Plusieurs agents, un numéro',
        'nav_sub' => 'Plusieurs agents sur un numéro WhatsApp Business',
        'toc' => [
            0 => 'La limite',
            1 => 'Le modèle',
            2 => 'La prise en charge',
            3 => 'Le routage',
            4 => 'Les sièges',
            5 => 'Faire la bascule',
            6 => 'FAQ',
        ],
        'faq' => [
            0 => [
                'q' => 'Combien d’agents peuvent utiliser un même numéro WhatsApp ?',
                'a' => [
                    0 => 'Autant que votre offre compte de sièges. Starter inclut 3 utilisateurs, Growth 5 et Scale 25, et vous pouvez ajouter des sièges à n’importe quelle offre sans changer de formule.',
                    1 => 'Tous travaillent depuis le même numéro connecté.',
                ],
            ],
            1 => [
                'q' => 'Les agents doivent-ils installer l’application WhatsApp ?',
                'a' => [
                    0 => 'Non. Les agents travaillent entièrement dans wavadesk depuis un navigateur. Seule la connexion initiale par QR code nécessite le téléphone qui détient le compte WhatsApp Business.',
                ],
            ],
            2 => [
                'q' => 'Qu’est-ce qui empêche deux agents de répondre en même temps ?',
                'a' => [
                    0 => 'La prise en charge. Prendre une conversation écrit un verrou qui empêche toute autre personne d’y écrire, appliqué en base de données et pas seulement dans l’interface.',
                ],
            ],
            3 => [
                'q' => 'Puis-je limiter ce qu’un agent voit ?',
                'a' => [
                    0 => 'Oui. Avec le routage par équipe, la file de chaque agent ne contient que les conversations dont son équipe est responsable, plutôt que tout ce qui arrive sur le numéro.',
                ],
            ],
            4 => [
                'q' => 'S’agit-il de l’API officielle WhatsApp Business ?',
                'a' => [
                    0 => 'wavadesk connecte votre numéro WhatsApp Business existant par QR code, le même mécanisme que WhatsApp Web : il n’y a donc pas de dossier API séparé, pas de validation de modèles de messages et pas de frais Meta par conversation.',
                ],
            ],
            5 => [
                'q' => 'Que se passe-t-il si un agent se déconnecte en pleine conversation ?',
                'a' => [
                    0 => 'La conversation lui reste attribuée et demeure visible par l’équipe. Un responsable peut la réaffecter, et la réaffectation est enregistrée pour que le passage de relais ne soit pas invisible.',
                ],
            ],
        ],
    ],
    'ai-agent' => [
        'title' => 'Agent IA pour le support client WhatsApp | wavadesk',
        'description' => 'Une IA qui répond à partir de votre propre base de connaissances, en mode suggestion, autonome ou hybride, avec des mots-clés d’escalade qui passent la main à un humain.',
        'h1' => 'Une IA qui répond à partir de <em>vos</em> connaissances, pas d’Internet',
        'lede' => 'L’essentiel de ce que votre équipe écrit chaque jour, elle l’a déjà écrit. L’agent IA de wavadesk rédige ces réponses à partir de votre propre FAQ, dans vos propres mots — et s’efface dès qu’une conversation demande une personne.',
        'kicker' => '',
        'nav_title' => 'Agent IA',
        'nav_sub' => 'Des réponses rédigées depuis votre base de connaissances',
        'toc' => [
            0 => 'Ce qu’il fait',
            1 => 'Les trois modes',
            2 => 'Rester exact',
            3 => 'L’escalade',
            4 => 'Le mesurer',
            5 => 'Tarification',
            6 => 'FAQ',
        ],
        'faq' => [
            0 => [
                'q' => 'L’IA va-t-elle répondre à mes clients sans mon accord ?',
                'a' => [
                    0 => 'Uniquement si vous choisissez le mode autonome ou hybride. En mode suggestion — celui par lequel nous recommandons de commencer — l’IA rédige et un humain envoie. Vous pouvez changer de mode à tout moment.',
                ],
            ],
            1 => [
                'q' => 'Où l’IA trouve-t-elle ses réponses ?',
                'a' => [
                    0 => 'Dans la base de connaissances que vous alimentez : votre FAQ, vos politiques, vos conditions de livraison et vos horaires. Elle est ancrée dans vos propres contenus plutôt que dans des connaissances générales du web, elle répond donc dans vos termes et dans le cadre de vos politiques.',
                ],
            ],
            2 => [
                'q' => 'Et si elle ne connaît pas la réponse ?',
                'a' => [
                    0 => 'Elle escalade vers un humain au lieu de deviner. Un message qui sort de vos connaissances, ou qui correspond à un mot-clé d’escalade, est envoyé dans la file de l’équipe concernée pour qu’un agent le prenne en charge.',
                ],
            ],
            3 => [
                'q' => 'Les clients peuvent-ils savoir qu’ils parlent à une IA ?',
                'a' => [
                    0 => 'Les réponses IA sont clairement identifiées dans votre boîte de réception, pour votre équipe comme pour un auditeur. Ce que vous annoncez aux clients relève de votre choix, et beaucoup d’équipes le précisent dans leur message d’accueil.',
                ],
            ],
            4 => [
                'q' => 'Que se passe-t-il quand j’atteins mon quota de messages IA ?',
                'a' => [
                    0 => 'Les réponses IA se mettent en pause et les conversations continuent normalement vers vos agents : rien ne casse et aucun client ne reste sans réponse. Vous pouvez ajouter un pack de messages en cours de cycle pour reprendre, ou passer à Scale pour l’illimité.',
                ],
            ],
            5 => [
                'q' => 'Combien de temps faut-il pour la mettre en place ?',
                'a' => [
                    0 => 'Coller une FAQ existante prend quelques minutes et suffit pour démarrer en mode suggestion. La plupart des équipes passent ensuite une semaine à affiner, en s’appuyant sur ce que les brouillons révèlent des trous dans leur documentation.',
                ],
            ],
        ],
    ],
    'live-chat-widget' => [
        'title' => 'Widget de chat en direct pour votre site | wavadesk',
        'description' => 'Ajoutez un chat en direct sur votre site qui arrive dans la même boîte de réception partagée que vos conversations WhatsApp, avec le contexte du visiteur et la même IA.',
        'h1' => 'Le chat de votre site dans la <em>même boîte</em> que WhatsApp',
        'lede' => 'Un second canal ne devrait pas signifier une seconde file. Le widget wavadesk place les conversations du site dans le pool partagé que votre équipe traite déjà, avec la même prise en charge, la même IA et le même historique.',
        'kicker' => '',
        'nav_title' => 'Widget de chat',
        'nav_sub' => 'Le chat du site dans la même boîte',
        'toc' => [
            0 => 'Pourquoi une seule boîte',
            1 => 'Installation',
            2 => 'Personnalisation',
            3 => 'Contexte visiteur',
            4 => 'L’IA sur le chat',
            5 => 'Performance',
            6 => 'FAQ',
        ],
        'faq' => [
            0 => [
                'q' => 'Les chats du site et WhatsApp arrivent-ils au même endroit ?',
                'a' => [
                    0 => 'Oui. Les deux arrivent dans la même boîte partagée, dans une seule file, avec un badge indiquant le canal d’origine. Les agents prennent en charge et répondent de la même façon quel que soit le canal.',
                ],
            ],
            1 => [
                'q' => 'Comment installer le widget ?',
                'a' => [
                    0 => 'Collez une seule balise script juste avant la balise de fermeture body de votre site. Cela fonctionne sur toute plateforme qui sert du HTML : sites statiques, WordPress, Shopify, Laravel, etc.',
                ],
            ],
            2 => [
                'q' => 'Puis-je changer l’apparence du widget ?',
                'a' => [
                    0 => 'Oui. Couleur d’accent, position, message d’accueil, champs de pré-chat et horaires de disponibilité sont tous configurables, avec un aperçu en direct à côté des réglages.',
                ],
            ],
            3 => [
                'q' => 'Que voient les agents d’un visiteur anonyme ?',
                'a' => [
                    0 => 'La session : page en cours, parcours de navigation, référent, navigateur, appareil et temps passé sur le site. Si le visiteur s’identifie, son historique sur les autres canaux est rattaché.',
                ],
            ],
            4 => [
                'q' => 'L’IA peut-elle répondre automatiquement aux chats du site ?',
                'a' => [
                    0 => 'Oui, et vous pouvez régler le mode par canal — par exemple autonome sur le widget pour des réponses immédiates, suggestion seule sur WhatsApp. Les mots-clés d’escalade s’appliquent aux deux.',
                ],
            ],
            5 => [
                'q' => 'Le widget va-t-il ralentir mon site ?',
                'a' => [
                    0 => 'Il se charge de façon asynchrone et ne bloque pas le rendu. Le lanceur apparaît une fois votre page interactive.',
                ],
            ],
        ],
    ],
    'otp-service' => [
        'title' => 'Service OTP et vérification par WhatsApp | wavadesk',
        'description' => 'Envoyez des codes à usage unique par WhatsApp plutôt que par SMS. Meilleurs taux de délivrance, coût par message plus faible et rapports de livraison complets.',
        'h1' => 'Envoyez vos codes à usage unique par <em>WhatsApp</em>, pas par SMS',
        'lede' => 'La vérification par SMS coûte cher, reste lente sur certains réseaux et passe facilement inaperçue. Le service OTP de wavadesk délivre les codes via le numéro WhatsApp sur lequel vos clients vous parlent déjà.',
        'kicker' => '',
        'nav_title' => 'OTP et vérification',
        'nav_sub' => 'Des codes de vérification par WhatsApp',
        'toc' => [
            0 => 'Ce que fait le module',
            1 => 'Pourquoi pas le SMS',
            2 => 'Comment ça marche',
            3 => 'Sécurité',
            4 => 'Rapports',
            5 => 'Cas d’usage',
            6 => 'FAQ',
        ],
        'faq' => [
            0 => [
                'q' => 'Envoyer des OTP par WhatsApp est-il sécurisé ?',
                'a' => [
                    0 => 'Le transport est chiffré de bout en bout, les codes sont stockés hachés et ne sont jamais renvoyés par le point d’envoi, et chaque code est à usage unique avec une courte expiration et une limite de tentatives. Les limites de débit par numéro et par clé d’API sont actives par défaut.',
                ],
            ],
            1 => [
                'q' => 'Et si un client n’utilise pas WhatsApp ?',
                'a' => [
                    0 => 'Le module signale la non-délivrance, afin que votre backend puisse basculer vers le SMS ou l’e-mail. La plupart des équipes envoient d’abord par WhatsApp et conservent un SMS de secours pour la couverture.',
                ],
            ],
            2 => [
                'q' => 'Combien coûte chaque OTP ?',
                'a' => [
                    0 => 'Rien par message. Les conversations WhatsApp sont illimitées sur toutes les offres, les vérifications supplémentaires n’ajoutent donc aucun coût. Le module OTP lui-même est inclus dans Scale et disponible en option sur les autres offres.',
                ],
            ],
            3 => [
                'q' => 'Combien de temps un code reste-t-il valide ?',
                'a' => [
                    0 => 'Vous configurez la fenêtre d’expiration, et les codes sont refusés une fois celle-ci dépassée. Une fenêtre courte est le réglage le plus sûr par défaut ; ne l’élargissez que si vous constatez que des utilisateurs légitimes arrivent à expiration.',
                ],
            ],
            4 => [
                'q' => 'Puis-je savoir si le code a bien été délivré ?',
                'a' => [
                    0 => 'Oui. WhatsApp fournit les statuts de livraison et de lecture, et les rapports affichent les volumes envoyés, délivrés et vérifiés pour repérer les abandons dans l’entonnoir de vérification.',
                ],
            ],
            5 => [
                'q' => 'Faut-il un numéro WhatsApp distinct pour les OTP ?',
                'a' => [
                    0 => 'Non. Les codes partent du même numéro professionnel connecté auquel vos clients écrivent déjà, ce qui explique en partie pourquoi ils inspirent plus confiance qu’un numéro court inconnu.',
                ],
            ],
        ],
    ],
    'reservations' => [
        'title' => 'Réservations et prise de rendez-vous sur WhatsApp | wavadesk',
        'description' => 'Prenez les réservations dans la conversation que votre client a déjà entamée, et réduisez les absences grâce aux rappels WhatsApp automatiques.',
        'h1' => 'Prenez les réservations <em>dans la conversation</em>, pas sur un formulaire',
        'lede' => 'La plupart des systèmes de réservation demandent au client de quitter la conversation pour remplir un formulaire. Le module de réservations la garde là où il se trouve déjà : le rendez-vous est convenu dans le chat, bloqué dans votre agenda et confirmé par un rappel automatique.',
        'kicker' => '',
        'nav_title' => 'Réservations',
        'nav_sub' => 'Des réservations dans la conversation',
        'toc' => [
            0 => 'Pourquoi WhatsApp',
            1 => 'Le parcours de réservation',
            2 => 'Les absences',
            3 => 'L’agenda',
            4 => 'La vérification',
            5 => 'À qui cela s’adresse',
            6 => 'FAQ',
        ],
        'faq' => [
            0 => [
                'q' => 'Les clients peuvent-ils réserver sans quitter WhatsApp ?',
                'a' => [
                    0 => 'Oui. C’est tout l’intérêt du module : les disponibilités sont proposées et le créneau confirmé dans la conversation, sans redirection vers une page de réservation.',
                ],
            ],
            1 => [
                'q' => 'Le module évite-t-il les doubles réservations ?',
                'a' => [
                    0 => 'Oui. Les disponibilités proviennent de vos horaires, de la durée des prestations, de vos ressources et des réservations existantes : un créneau pris n’est jamais reproposé, que la proposition vienne de l’IA ou d’un membre de l’équipe.',
                ],
            ],
            2 => [
                'q' => 'L’IA peut-elle gérer seule les réservations ?',
                'a' => [
                    0 => 'Elle gère le parcours courant : proposer les disponibilités, enregistrer un choix et confirmer. Tout cas inhabituel — un praticien précis, une situation particulière — est escaladé vers un agent qui réserve manuellement.',
                ],
            ],
            3 => [
                'q' => 'Comment fonctionnent les rappels ?',
                'a' => [
                    0 => 'Une confirmation immédiate, puis des rappels selon le calendrier que vous définissez, en général 24 heures et 2 heures avant. Ils arrivent dans le fil WhatsApp existant et proposent de confirmer ou de reprogrammer en réponse directe.',
                ],
            ],
            4 => [
                'q' => 'Puis-je exiger une vérification du téléphone avant de bloquer un créneau ?',
                'a' => [
                    0 => 'Oui, en associant les réservations au module OTP. Le client confirme son numéro avec un code à usage unique avant que la réservation ne soit bloquée, ce qui élimine les réservations fictives et les numéros mal saisis.',
                ],
            ],
            5 => [
                'q' => 'Cela fonctionne-t-il avec plusieurs employés ou salles ?',
                'a' => [
                    0 => 'Oui. Chaque ressource — praticien, fauteuil, poste ou table — possède son propre planning et sa propre capacité, et les réservations leur sont affectées.',
                ],
            ],
        ],
    ],
    'knowledge-base' => [
        'title' => 'Base de connaissances et réponses enregistrées | wavadesk',
        'description' => 'La source unique dont votre IA tire ses réponses et que vos agents réutilisent. Écrivez une réponse une fois et elle sert aux réponses automatiques, aux raccourcis des agents et à l’autonomie des clients.',
        'h1' => 'Écrivez la réponse <em>une seule fois</em>. Utilisez-la partout.',
        'lede' => 'Votre équipe connaît déjà les réponses — elles sont simplement éparpillées dans la tête des uns, d’anciens historiques de chat et un document que personne n’a mis à jour. La base de connaissances en fait la source unique dont votre IA tire ses réponses et que vos agents réutilisent.',
        'kicker' => '',
        'nav_title' => 'Base de connaissances',
        'nav_sub' => 'Écrivez la réponse une fois, utilisez-la partout',
        'toc' => [
            0 => 'Une source unique',
            1 => 'La structure',
            2 => 'Alimenter l’IA',
            3 => 'Réponses enregistrées',
            4 => 'La maintenance',
            5 => 'FAQ',
        ],
        'faq' => [
            0 => [
                'q' => 'Quel format dois-je importer ?',
                'a' => [
                    0 => 'Du texte brut ou une FAQ collée suffisent pour démarrer. Des entrées courtes, sur un seul sujet et rédigées comme vous écririez à un client fonctionnent mieux que de longs documents de politique interne.',
                ],
            ],
            1 => [
                'q' => 'L’IA et mes agents utilisent-ils la même source ?',
                'a' => [
                    0 => 'Oui, c’est le principe : un seul référentiel alimente les réponses de l’IA et les réponses enregistrées des agents, si bien qu’une réponse automatique et une réponse humaine ne peuvent pas se contredire.',
                ],
            ],
            2 => [
                'q' => 'De combien de contenu ai-je besoin avant d’activer l’IA ?',
                'a' => [
                    0 => 'Vos vingt questions les plus fréquentes constituent un point de départ réaliste et couvrent l’essentiel du volume. Fonctionnez ensuite en mode suggestion et laissez les brouillons révéler ce qui manque.',
                ],
            ],
            3 => [
                'q' => 'Que se passe-t-il quand l’IA ne trouve pas de réponse ?',
                'a' => [
                    0 => 'Elle escalade vers un humain plutôt que de deviner. Chaque escalade récurrente indique quelle entrée rédiger ensuite.',
                ],
            ],
            4 => [
                'q' => 'Chaque équipe peut-elle avoir ses propres entrées ?',
                'a' => [
                    0 => 'Oui. Les entrées et les réponses enregistrées peuvent être limitées à une équipe, pour que chaque file ne voie que le contenu qui la concerne.',
                ],
            ],
            5 => [
                'q' => 'Les clients peuvent-ils consulter directement la base de connaissances ?',
                'a' => [
                    0 => 'Le contenu en libre-service figure sur la feuille de route. Aujourd’hui, la base de connaissances alimente les réponses de l’IA et les réponses enregistrées des agents à l’intérieur de votre espace de travail.',
                ],
            ],
        ],
    ],
    'teams-routing' => [
        'title' => 'Équipes, routage et affectation pour le support | wavadesk',
        'description' => 'Envoyez chaque conversation à l’équipe qui en a la charge. Routez par équipe, par mot-clé et par horaires, avec escalade quand une conversation vieillit.',
        'h1' => 'Routez chaque conversation vers <em>l’équipe qui en a la charge</em>',
        'lede' => 'Une file partagée unique convient à trois agents et ne convient plus à quinze. Le routage donne à chaque équipe le sous-ensemble de conversations sur lesquelles elle peut réellement agir, pour que personne ne fasse défiler trente tickets qui ne le concernent pas.',
        'kicker' => '',
        'nav_title' => 'Équipes et routage',
        'nav_sub' => 'Envoyez le travail à l’équipe concernée',
        'toc' => [
            0 => 'Pourquoi router',
            1 => 'Les équipes',
            2 => 'Les règles de routage',
            3 => 'L’affectation',
            4 => 'L’escalade',
            5 => 'Le mesurer',
            6 => 'FAQ',
        ],
        'faq' => [
            0 => [
                'q' => 'Ai-je besoin du routage tout de suite ?',
                'a' => [
                    0 => 'Non. Commencez avec un pool partagé unique et la prise en charge. Le routage devient rentable autour de cinq ou six agents, voire plus tôt si vos équipes traitent des sujets réellement différents.',
                ],
            ],
            1 => [
                'q' => 'Un agent peut-il appartenir à plusieurs équipes ?',
                'a' => [
                    0 => 'Oui. Les petites équipes mettent souvent tout le monde dans toutes les équipes au départ, puis resserrent les frontières à mesure que le trafic montre où elles se situent vraiment.',
                ],
            ],
            2 => [
                'q' => 'Que devient une conversation qui ne correspond à aucune règle ?',
                'a' => [
                    0 => 'Elle arrive dans votre file par défaut. Toute configuration de routage devrait en avoir une, sinon les conversations non appariées n’ont nulle part où aller.',
                ],
            ],
            3 => [
                'q' => 'Faut-il utiliser l’affectation automatique ou la prise en charge ?',
                'a' => [
                    0 => 'La prise en charge, dans la plupart des cas. Le tourniquet distribue le travail à des agents peut-être déjà en pleine conversation et leur retire la possibilité de prendre ce qu’ils sont le mieux placés pour traiter. L’affectation automatique fonctionne là où le statut de disponibilité est fiable.',
                ],
            ],
            4 => [
                'q' => 'Comment éviter que des conversations restent sans prise en charge ?',
                'a' => [
                    0 => 'Définissez un seuil d’ancienneté et escaladez en cas de dépassement — vers la file d’un responsable ou en tête de la file d’équipe. La boîte de réception met aussi en avant la conversation qui attend depuis le plus longtemps, pour que le problème soit visible tôt.',
                ],
            ],
            5 => [
                'q' => 'Puis-je router par langue ?',
                'a' => [
                    0 => 'Oui. Les équipes peuvent être organisées par langue et routées en conséquence, ce qui compte lorsque tous les agents ne couvrent pas toutes les langues que vous servez.',
                ],
            ],
        ],
    ],
    'reports-analytics' => [
        'title' => 'Rapports et statistiques du support | wavadesk',
        'description' => 'Médiane de première réponse, temps de résolution, volume par canal, charge des agents et déflexion IA — les chiffres sur lesquels vous pouvez réellement piloter.',
        'h1' => 'Les chiffres du support sur lesquels vous pouvez <em>réellement piloter</em>',
        'lede' => 'La plupart des équipes qui gèrent du support WhatsApp n’ont aucun chiffre — elles ont des impressions. Les rapports transforment le temps de réponse, le volume et la charge en quelque chose sur quoi fixer un objectif et voir bouger l’aiguille.',
        'kicker' => '',
        'nav_title' => 'Rapports et statistiques',
        'nav_sub' => 'Temps de réponse, volume, charge de travail',
        'toc' => [
            0 => 'L’angle mort',
            1 => 'Les indicateurs clés',
            2 => 'Le volume',
            3 => 'Le temps de réponse',
            4 => 'La charge de travail',
            5 => 'La performance de l’IA',
            6 => 'La revue hebdomadaire',
            7 => 'FAQ',
        ],
        'faq' => [
            0 => [
                'q' => 'Quels indicateurs sont inclus ?',
                'a' => [
                    0 => 'Le volume de conversations par canal et par jour, la médiane de première réponse, le temps de résolution, les conversations en attente dans le pool avec l’attente la plus ancienne, la charge par agent rapportée à sa capacité, les heures de pointe par jour et par heure, et la déflexion IA répartie entre résolu, assisté et escaladé.',
                ],
            ],
            1 => [
                'q' => 'Les rapports sont-ils disponibles sur toutes les offres ?',
                'a' => [
                    0 => 'Les rapports de volume, de temps de réponse et d’IA sont disponibles sur toutes les offres. Les répartitions par équipe et l’ensemble complet des rapports arrivent avec Growth et au-delà.',
                ],
            ],
            2 => [
                'q' => 'Pourquoi la médiane de première réponse et non la moyenne ?',
                'a' => [
                    0 => 'Quelques conversations arrivées pendant la nuit et traitées au matin suffisent à rendre une moyenne dénuée de sens. La médiane reflète ce que vit réellement un client typique.',
                ],
            ],
            3 => [
                'q' => 'Puis-je exporter les données ?',
                'a' => [
                    0 => 'Oui. Les rapports peuvent être exportés sur la période de votre choix, afin de les croiser avec d’autres données métier ou de conserver un historique plus long en dehors de l’espace de travail.',
                ],
            ],
            4 => [
                'q' => 'L’IA compte-t-elle comme un agent dans les rapports de charge ?',
                'a' => [
                    0 => 'Non. L’activité de l’IA est rapportée séparément sous forme de déflexion, pour que les chiffres de charge humaine ne soient pas embellis par des réponses automatiques.',
                ],
            ],
            5 => [
                'q' => 'Jusqu’où remonte l’historique ?',
                'a' => [
                    0 => 'L’intégralité de l’historique de votre espace de travail est conservée et exploitable. Le tableau de bord affiche par défaut les 14 derniers jours, avec des vues 7 et 30 jours sélectionnables.',
                ],
            ],
        ],
    ],
    'api-integrations' => [
        'title' => 'API et intégrations | wavadesk',
        'description' => 'Faites circuler conversations, contacts et événements entre wavadesk et vos outils. Points de terminaison REST, webhooks et API OTP.',
        'h1' => 'Connectez wavadesk au <em>reste de vos outils</em>',
        'lede' => 'Le support ne vit pas en vase clos. L’API et les webhooks permettent à vos propres systèmes de lire les conversations, de créer des contacts, d’envoyer des codes de vérification et de réagir aux événements en temps réel.',
        'kicker' => '',
        'nav_title' => 'API et intégrations',
        'nav_sub' => 'REST, webhooks et API OTP',
        'toc' => [
            0 => 'Pourquoi intégrer',
            1 => 'L’authentification',
            2 => 'L’API REST',
            3 => 'Les webhooks',
            4 => 'L’API OTP',
            5 => 'L’intégration',
            6 => 'Les limites',
            7 => 'FAQ',
        ],
        'faq' => [
            0 => [
                'q' => 'Quelle offre inclut l’accès à l’API ?',
                'a' => [
                    0 => 'L’accès à l’API, les webhooks et les points de terminaison OTP sont inclus dans l’offre Scale. Les autres offres peuvent ajouter le module OTP séparément.',
                ],
            ],
            1 => [
                'q' => 'Comment s’authentifier ?',
                'a' => [
                    0 => 'Avec un jeton porteur limité à un espace de travail, créé dans les réglages. Les jetons sont révocables, limités à un seul espace de travail, et doivent rester côté serveur — jamais dans du code front-end.',
                ],
            ],
            2 => [
                'q' => 'Puis-je pousser mes propres données client dans wavadesk ?',
                'a' => [
                    0 => 'Oui, et c’est en général la première intégration qui vaut la peine d’être construite. Écrire des champs personnalisés sur la fiche contact permet aux agents de voir votre historique de commandes, l’offre ou le statut du compte dans le panneau de contexte pendant qu’ils répondent.',
                ],
            ],
            3 => [
                'q' => 'Quels événements les webhooks peuvent-ils envoyer ?',
                'a' => [
                    0 => 'Conversation créée, prise en charge, résolue et escaladée ; message reçu et envoyé ; contact créé et mis à jour ; et OTP vérifié. Les charges utiles sont signées pour que vous puissiez les vérifier.',
                ],
            ],
            4 => [
                'q' => 'Que se passe-t-il si mon point de terminaison webhook est indisponible ?',
                'a' => [
                    0 => 'Les livraisons sont réessayées avec un délai croissant. Répondez rapidement en 2xx et traitez de façon asynchrone : un traitement lent dans la requête provoquera une expiration et des livraisons en double.',
                ],
            ],
            5 => [
                'q' => 'L’API peut-elle changer sans préavis ?',
                'a' => [
                    0 => 'Les changements cassants sont livrés derrière une version et les versions existantes restent prises en charge. Les ajouts, comme de nouveaux champs ou de nouveaux types d’événements, peuvent arriver sans changement de version : analysez donc les charges utiles de façon défensive.',
                ],
            ],
        ],
    ],
];
