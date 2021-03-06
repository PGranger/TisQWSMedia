<?php
	
	/**
	*	Fichier d'exemple de l'utilisation de la classe TisQWSMedias.
	*
	*	Le but est de créer, dans un dossier défini au départ, un sous dossier pour chaque offre, dans lequel on stockera toutes les photos concernant l'offre.
	*	Chaque sous-dossier sera créé s'il n'existe pas encore, et vidé s'il existe déjà, avant l'ajout des photos de l'offre.
	*	Toutes les photos ajoutées seront ensuite associées à l'offre.
	*	L'utilisation de cet exemple nécessite à minima de remplir la variable $photos selon la structure suivante : 
	*		Ex :
	*		$photos = Array(
	*			'HLOAUV0030011177' => Array(
	*				Array(
	*					'url' => 'http://www..../photo1/G11177.jpg',
	*					'textes' => Array('fr-FR'=>Array('titre'=>'Piscine et jardin','credit'=>'Gîtes de France'))
	*				),
	*				Array(
	*					'url' => '/home/..../photo2.jpg',
	*					'textes' => Array('fr-FR'=>Array('titre'=>'Salle de bains','credit'=>'Gîtes de France'))
	*				)
	*			)
	*		) ;
	*	Pour pouvoir utiliser cet exemple et la classe TisQWSMedias, vous devrez impérativement au préalable créer un questionnaire web sur Tourinsoft, sur le bordereau souhaité, qui contiendra simplement les champs [Photo1], [Photo2]... avec le nombre d'occurrence de votre choix.
	*	Le questionnaire devra être publié en tant que questionnaire Webservice (et non questionnaire HTML) : voir http://documentation.tourinsoft.com/index.php/Questionnaire_web pour plus d'informations.
	*		Ex :
	*		[Photo1] [Photo2] [Photo3]... [Photo20]
	*	C'est dans la publication de ce questionnaire (Gen. Web > Questionnaire Web > Gestion de la publication) que vous trouverez la plupart des identifiants requis ci-dessous (questionnaireId, structureId, client...).
	*/
	error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE) ;
	ini_set("display_errors", 1) ;
	set_time_limit(0) ;
	ini_set ('max_execution_time', 0) ;
	
	require_once(realpath(dirname(__FILE__)).'/TisQWSMedias.class.php') ;
	
	/**
	*	Si on exécute ce fichier en ligne de commande, on affiche la sortie directement dans la console (on n'attend pas la fin de l'exécution pour le faire)
	*	Ca permet de controler où en est l'exécution du fichier voir de la stopper en cas de problème (CTRL+C)
	*/
	if ( isset($_SERVER['SSH_CLIENT']) ) @ob_end_flush() ; else echo '<pre>' ;
	
	/**
	*	config.inc.php contient simplement les variables $cfg_XXX avec les identifiants (structure, questionnaire, client, utilisateur...).
	*	Bien entendu vous pouvez les saisir directement ci-dessous ($questionnaireId = 'XXXX-....'). Je les ai séparés simplement pour les sortir du depôt Git.
	*	Consulter la documentation de TisQWSMedias.class.php pour savoir où récupérer les différents identifiant sur Tourinsoft :
	*	http://cdt.allier-auvergne-tourisme.com/TisQWSMedia/docs/classes/TisQWSMedias.html#properties
	*/
	require_once(realpath(dirname(__FILE__).'/../').'/config.inc.php') ;
	$questionnaireId = $cfg_questionnaireId ; // 'XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX'
	$client = $cfg_client ; // ex : 'cdt03'
	$structureId = $cfg_structureId ; // 'XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX'
	$dossierId = $cfg_dossierId ; // 'XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX'
	$utilisateurId = $cfg_utilisateurId ; // 'XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX'
	$mail_administrateur = $cfg_mail_administrateur ;
	/**
	*	Activez le debug pour afficher tous les messages à l'écran.
	*	En cas d'erreur aucun mail ne sera envoyé à mail_administrateur.
	*/
	$debug = true ;
	
	$ws = new TisQWSMedias(Array(
		'questionnaireId'=>$questionnaireId,
		'client'=>$client,
		'structureId'=>$structureId,
		'utilisateurId'=>$utilisateurId,
		'debug'=>$debug,
		'mail_administrateur'=>$mail_administrateur
	)) ;
	
	/**
	*	Cette partie dépend de ce que vous voulez faire.
	*	Voir dans la description du fichier exemple.php (en haut du fichier) pour le détail de la structure de la variable $photos.
	*	Dans l'exemple la variable est remplie dans un fichier build_photos.inc.php qui n'est pas fourni. Vous pouvez donc retirer la ligne le concernant et remplir $photos à la place.
	*/
	$photos = Array() ;
	include(realpath(dirname(__FILE__).'/../').'/build_photos.inc.php') ; // à remplacer par votre propre façon de remplir $photos selon la structure décrite en haut de ce fichier
	
	/**
	*	On va parcourir tous les "dossiers" à créer.
	*	Ici on considère que, dans le dossier racine déclaré au départ, $dossierId, on crée un sous dossier pour chaque offre (Nom du dossier = Identifiant TIF de l'offre).
	*	Le but au final est d'obtenir une arborescence de ce genre :
	*		Images
	*		|	GDF // correspond au $dossierId ci-dessus
	*		|	|	HLOAUV0030011111
	*		|	|	|	photo1.jpg
	*		|	|	|	photo2.jpg
	*		|	|	|	photo3.jpg
	*		|	|	|	...
	*		|	|	HLOAUV0030011112
	*		|	|	|	photo1.jpg
	*		|	|	|	photo2.jpg
	*		|	|	|	photo3.jpg
	*		|	|	|	...
	*		|	|	...
	*/
	foreach ( $photos as $idTif_offre => $images )
	{
		if ( ! is_array($images) || sizeof($images) == 0 ) continue ;
		
		// On récupère le dossier correspondant. S'il existe, on le vide (viderDossier=true). S'il n'existe pas, on le crée (create=true)
		if ( ! $offre_dossierId = $ws->getDossier(Array('dossierParentId'=>$dossierId,'dossierNom'=>$idTif_offre,'viderDossier'=>true,'creerDossier'=>true)) )
		{
			$ws->debug('Impossible de récupérer le dossier pour '.$idTif_offre) ;
			continue ;
		}
		
		/**
		*	On a le numéro du dossier, il ne reste plus qu'à envoyer nos différentes images
		*	On envoie les images dans le dossier qu'on vient de créer avec $ws->envoyerFicher, qui nous renvoie le GUID de l'image importée
		*	Tous les GUID renvoyés sont stockés dans $guids
		*	Une fois toutes les images envoyées, on associe tous les GUID à l'offre
		*/
		$guids = Array() ;
		foreach ( $images as $i => $image )
		{
			/**
			*	$image doit être un tableau de 2 valeurs (url et textes)
			*		Array(
			*			'url' => 'http://www..../photo1/G11177.jpg',
			*			'textes' => Array('fr-FR'=>Array('titre'=>'Piscine et jardin','credit'=>'Gîtes de France'))
			*		)
			*/
			if ( sizeof($image) != 2 || ! isset($image['url']) || ! isset($image['textes']) || ! is_array($image['textes']) )
				{ $ws->debug('L\'image '.$i.' pour l\'offre '.$idTif_offre.' est mal construite (devrait être un tableau de 2 entrées) : '.print_r($image,true)) ; continue ; }
			
			$langues = Array() ;
			foreach ( $textes as $lng_iso => $valeurs )
				$langues[] = Array('titre'=>$valeurs['titre'],'credit'=>$valeurs['credit'],'langueID'=>$lng_iso) ;
			
			if ( $guid = $ws->envoyerFichier($image['url'],$offre_dossierId,$langues) )
				$guids[] = $guid ;
		}
		/**
		*	On associe tous les guids avec l'offre $idTif_offre
		*/
		if ( sizeof($guids) > 0 )
		{
			if ( ! $ws->associerFichiersOffres($idTif_offre,$guids) )
				$ws->debug('Association des guids '.implode(', ',$guids).' avec '.$idTif_offre.' impossible') ;
		}
	}
	