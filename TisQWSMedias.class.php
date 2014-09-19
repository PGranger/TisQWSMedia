<?php
	
	/**
	*	Fichier comprenant uniquement la classe TisQWSMedias
	*
	*	Et qui ne nécessite du coup pas grand commentaire
	*/
		
	class TisQWSMedias {
		
		/**
		*	Classe d'utilisation du webservice ImportMediaService de Tourinsoft V5
		*
		*	Classe créée pour simplifier la création/suppression de dossiers et d'images (association aux offres comprise) dans la médiathèque de Tourinsoft à l'aide du webservice ImportMediaService.
		*	Voir la documentation en ligne du webservice pour plus de détails : http://api-doc.tourinsoft.com/#/questionnaire-web#api-services-medias
		*	Cette classe est fournie en l'état, sans garantie de fonctionnement, sous licence MIT : vous pouvez la réutiliser et la modifier à votre guise, sans nécessairement fournir votre propre code source (même si toute participation est la bienvenue).
		*
		*	@author	Pierre Granger <p.granger@allier-tourisme.net>
		*	@licence	MIT Licence
		*	@link	http://www.allier-auvergne-tourisme.com
		*	@link	http://www.pierre-granger.fr
		*	@link	http://api-doc.tourinsoft.com/#/questionnaire-web#api-services-medias
		*	@version	v1.0
		*	@date	2014-09-18
		*/

		/**
		*	@var	guid	GUID du questionnaire. Vous le trouverez dans Tourinsoft dans : Gen. Web > Questionnaire Web > Gestion de la publication > *Votre questionnaire* > Tout en bas
		*/
		private $questionnaireId ;
		/**
		*	@var	string	Nom de votre structure sur Tourinsoft. Le plus souvent, [xxx].tourinsoft.com (ex: cdt03.tourinsoft.com)
		*/
		private $client ;
		/**
		*	@var	guid	GUID de votre structure. Comme pour questionnaireId, en bas de la modification de la publication du questionnaire web : choisissez votre structure dans le menu déroulant puis le GUID correspondant s'affichera en dessous (Structure Id : XXX...)
		*/
		private $structureId ;
		/**
		*	@var	guid	GUID de l'utilisateur (pour savoir quels dossiers et fichiers de la médiathèque vous avez le droit de voir ou non). Un peu plus dur à trouver : dans Admin > Utilisateur, trouvez votre utilisateur dans la liste, puis placez le curseur de souris sur le bouton "modifier", puis clic droit, copier l'adresse du lien : ça doit ressembler à ça :  http://cdt03.tourinsoft.com/t.../general.aspx?id=XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX. Il faut récupérer le GUID tout à la fin (id=...)
		*/
		private $utilisateurId ;
		
		/**
		*	@var	int	les services uploadFile et uploadFileNext recoivent les fichiers par "paquet" et non en une seule fois. On définit la taille de ce paquet ici (en byte)
		*/
		private $packet_size = 262144 ; // 1024
		/**
		*	@var	bool	Affichera le déroulement du script dans la console (SSH) ou à l'écran (http)
		*/
		private $debug = false ;
		/**
		*	@var	string	Adresse mail à qui seront envoyées les erreurs si debug OFF
		*/
		private $mail_administrateur ;
		/**
		*	@var	string	Adresse mail de l'expéditeur des erreurs si debug OFF
		*/
		private $mail_expediteur ;
		/**
		*	@var	string	URL du webservice questionnaire web
		*/
		private $url_wcf_qweb = "http://wcf.tourinsoft.com/QuestionnaireWeb/QuestionnaireWebService.svc?wsdl" ;
		/**
		*	@var	string	URL du webservice mediathèque
		*/
		private $url_wcf_media = "http://wcf.tourinsoft.com/Mediatheque/ImportMediaService.svc?wsdl" ;
		/**
		*	@var	object	Implémentation (soap) du webservice $url_wcf_qweb
		*/
		private $soap ;
		/**
		*	@var	object	Implémentation (soap) du webservice $url_wcf_media
		*/
		private $soap_media ;
		
		/**
		*	Constructeur
		*	@param	array	$params	Liste des paramètres du constructeur. Voir les variables privées de la classe pour la description détaillée
		*					$params['questionnaireId']	guid	(obligatoire)
		*					$params['client']	string	(obligatoire)
		*					$params['structureId']	guid	(obligatoire)
		*					$params['utilisateurId']	guid	(obligatoire)
		*					$params['utilisateurId']	guid	(obligatoire)
		*/
		public function __construct($params)
		{
			if ( ! is_array($params) ) return false ;
			if ( isset($params['questionnaireId']) ) $this->questionnaireId = $params['questionnaireId'] ; else return false ;
			if ( isset($params['client']) ) $this->client = $params['client'] ; else return false ;
			if ( isset($params['structureId']) ) $this->structureId = $params['structureId'] ; else return false ;
			if ( isset($params['utilisateurId']) ) $this->utilisateurId = $params['utilisateurId'] ;
			if ( isset($params['debug']) ) $this->debug = $params['debug'] ? true : false ;
			if ( isset($params['mail_administrateur']) && $this->check_mail($params['mail_administrateur']) ) $this->mail_administrateur = $params['mail_administrateur'] ;
			if ( isset($params['mail_expediteur']) && $this->check_mail($params['mail_expediteur']) ) $this->mail_expediteur = $params['mail_expediteur'] ;
			try { $this->soap = new SoapClient($this->url_wcf_qweb); } catch(SoapFault $fault) { $this->error($fault) ; }
			try { $this->soap_media = new SoapClient($this->url_wcf_media); } catch(SoapFault $fault) { $this->error($fault) ; }
		}
		
		/**
		*	Envoyer le fichier $path (local ou distant) dans le dossier $dossierId avec les textes contenus dans $langues
		*	@param	string	$path	Chemin du fichier à envoyer. Peut être une url (http://...) ou un fichier local (/home/...)
		*	@param	guid	$dossierId	Identifiant du dossier sur la médiathèque de Tourinsoft
		*	@param	array	$langues	Tableau des descriptions texte de l'image (titre, description...)
		*		Ex : Array(
		*				Array('titre'=>'Titre en Français','credit'=>'Crédit en Français','langueID'=>'fr-FR'),
		*				Array('titre'=>'Titre en Anglais','credit'=>'Crédit en Anglais','langueID'=>'en-EN')
		*			)
		*	@return	guid|false	Renvoie l'identifiant (guid) de l'image ajoutée à la médiathèque, ou false si une erreur s'est produite
		*/
		public function envoyerFichier($path,$dossierId,$langues)
		{
			$retour = false ;
			if ( ! $this->isGuid($dossierId) ) { $this->error('$dossierId !guid') ; return false ; }
			
			$this->debug("nTisQWSMedias->envoyerFichier(".$path.",".$dossierId.",[textes])") ;
			
			if ( ! ( $file_content = file_get_contents($path) ) ) { $this->error('! file_get_contents('.$path.')') ; return false ; }
			$base64_file = base64_encode($file_content) ;
			$base64_array = str_split($base64_file,$this->packet_size) ;
			
			$this->debug(sizeof($base64_array).' paquets à envoyer : [',false) ;
			
			$guid = null ;
			foreach ( $base64_array as $i => $data )
			{
				$this->debug('.',false) ;
				if ( $guid == null )
				{
					try {
						$uploadFileResponse = $this->soap_media->uploadFile(Array('client'=>$this->client,'data'=>$data,'questionnaireId'=>$this->questionnaireId)) ;
						$guid = (string) $uploadFileResponse->uploadFileResult ;
					} catch (SoapFault $fault) { $this->error($fault) ; }
				}
				else
				{
					try {
						$this->soap_media->uploadFileNext(Array('client'=>$this->client,'id'=>$guid,'data'=>$data,'questionnaireId'=>$this->questionnaireId)) ;
					} catch (SoapFault $fault) { $this->error($fault) ; }
				}
			}
			$this->debug(']',false) ;
			
			$info = Array(
				'dossierId' => $dossierId,
				'lgs' => $langues,
				'structureId' => $this->structureId
			) ;
			
			if ( $guid !== null )
			{
				$fileName = utf8_encode(basename($path)) ;
				$params = Array('client'=>$this->client,'id'=>$guid,'fileName'=>$fileName,'info'=>$info,'questionnaireId'=>$this->questionnaireId) ;
				
				try {
					$addMediaResponse = $this->soap_media->addMedia($params) ;
					$retour = (string) $addMediaResponse->addMediaResult ;
				} catch (SoapFault $fault) { $this->error($fault) ; }
			}
		
			$this->debug('TisQWSMedias->envoyerFichier() return '.(($retour)?$retour:'ko')) ;
			return $retour ;
		}
		
		/**
		*	associer toutes les images dont les identifiants (guid) sont contenus dans $guids ) l'offre $id_tis. Les guids sont récupérés de la médiathèque, par exemple après avoir été envoyés avec envoyerFichier.
		*	@param	tif	$id_tis	Identifiant TIF Tourinsoft de la fiche à associer
		*	@param	array	$guids	Tableau des guids des images ajoutées à la médiathèque (le plus souvent qui viennent d'être ajoutées avec envoyerFichier)
		*
		*	@return	true|false	L'enregistrement a fonctionné... ou pas
		*/
		public function associerFichiersOffres($id_tis,$guids)
		{
			$this->debug('TisQWSMedias->associerFichiersOffres('.$id_tis.','.var_export($guids,true).')') ;
			
			$retour = false ;
			if ( sizeof($guids) > 0 )
			{
				try {
					$champs = $this->soap->Get(Array('client'=>$this->client,'questionnaireId'=>$this->questionnaireId)) ;
					
					foreach ( $champs->GetResult->Champ as $i => $champ )
					{
						if ( $champ->TypeChamp == 'TypeString' && preg_match('#^Photo([0-9]+)$#Ui',$champ->Libelle,$match) )
						{
							$num_photo = $match[1] ;
							if ( isset($guids[$num_photo-1]) )
								$champs->GetResult->Champ[$i]->Valeur = $guids[$num_photo-1] ;
						}
					}
					
					try {
						$this->soap->Save(Array('client'=>$this->client,'questionnaireId'=>$this->questionnaireId,'offre'=>$id_tis,'champs'=>$champs->GetResult->Champ,'structureId'=>$this->structureId)) ;
						$retour = true ;
					} catch(SoapFault $fault) { $this->error($fault) ; }
					
				} catch(SoapFault $fault) { $this->error($fault) ; }
			}
			return $retour ;
		}
		
		/**
		*	Renvoie le GUID du dossier dont le nom est $params['dossierNom'], qui sera recherché dans le dossier dont le GUID est $params['dossierParent']
		*	@param	Array	$params	Contient tous les paramètres
		*							$params['dossierParentId']	(obligatoire)	GUID du dossier racine où rechercher le dossier
		*							$params['dossierNom']	(obligatoire)	Nom du dossier que l'ont recherche (ou que l'on crée)
		*	@return	guid|false	Retourne l'identifiant du dossier trouvée ou créé
		*/
		public function getDossier($params)
		{
			if ( isset($params['dossierParentId']) ) $dossierParentId = $params['dossierParentId'] ; else return false ;
			if ( isset($params['dossierNom']) ) $dossierNom = $params['dossierNom'] ; else return false ;
			if ( ! $this->isGuid($dossierParentId) ) return false ;
			
			try {
				$this->debug('getDossier('.$dossierParentId.','.$dossierNom.')') ;
				$res = $this->soap_media->getDossiers(Array(
					'client'=>$this->client,
					'questionnaireId'=>$this->questionnaireId,
					'structureId'=>$this->structureId,
					'utilisateurId'=>$this->utilisateurId,
					'dossierParentId'=>$dossierParentId
				)) ;
				$dossiers = $res->getDossiersResult->ImportMediaDossier ;
				
				foreach ( $dossiers as $dossier )
				{
					// Le dossier est trouvé
					if ( $dossierNom == $dossier->dossierNom )
					{
						var_dump($dossier->dossierId) ;
						if ( isset($params['viderDossier']) && $params['viderDossier'] === true )
							$this->viderDossier($dossier->dossierId) ;
						return $dossier->dossierId ;
					}
				}
				
				if ( isset($params['creerDossier']) && $params['creerDossier'] === true )
				{
					// Le dossier n'a pas été trouvé : on le crée
					$nouveauDossier = Array(
						'dossierNom' => $dossierNom,
						'dossierParentId' => $dossierParentId
					) ;
					if ( $ret = $this->soap_media->addDossier(Array(
						'client'=>$this->client,
						'questionnaireId'=>$this->questionnaireId,
						'structureId'=>$this->structureId,
						'info'=>$nouveauDossier
					)) )
					{
						if ( isset($ret->addDossierResult) )
							return $ret->addDossierResult ;
					}
				}
				
			} catch(SoapFault $fault) { $this->error($fault) ; }
			return false ;
		}
		
		/**
		*	Vide le dossier $dossierId des images qu'il contient
		*	@param	guid	$dossierId N.C.
		*	@param	array	$params	Paramètres supplémentaires
		*	@param	bool	$params['supprimerRacine']	Supprime le dossier $dossierID
		*	@param	bool	$params['supprimerSousDossiers']	Supprime les sous-dossiers de $dossierID
		*	@todo	interpréter $params['supprimerRacine']
		*	@todo	interpréter $params['supprimerSousDossiers']
		*/
		public function viderDossier($dossierId,$params=null)
		{
			// Refuser de supprimer si on a pas un vrai Guid
			// Ca ne protège pas de tout mais ça évitera au moins une suppression de la racine (pas testé si c'était faisable...)
			if ( ! $this->isGuid($dossierId) ) return false ;
			
			$guids = Array() ;
			$images = $this->getFichiers($dossierId) ;
			foreach ( $images as $img )
				if ( isset($img->elementId) && $this->isGuid($img->elementId) )
					$guids[] = $img->elementId ;
			
			if ( is_array($guids) && sizeof($guids) > 0 )
				$this->supprimerDocuments($guids) ;
			
			// On supprime le dossier qu'on vient de vider ?
			// TODO
			if ( is_array($params) && isset($params['supprimerRacine']) && $params['supprimerRacine'] === true )
			{
				
			}
			
			// On supprime également les sous dossiers (dangereux... à faire si besoin)
			// TODO
			if ( is_array($params) && isset($params['supprimerSousDossiers']) && $params['supprimerSousDossiers'] === true )
			{
				
			}
			return true ;
		}
		
		/**
		*	Supprime tous les documents dont le guid est contenu dans $guids, peu importe son emplacement dans les dossiers de la médiathèque.
		*	@param	guid|array	$guids	GUID du fichier, ou tableau des GUIDs des fichiers à supprimer
		*	@param	array	$params	Paramètres supplémentaires
		*	@param	string	$params['importMediaTypeAction']	SupressionMedia|SupressionAssociationOffreMedia|SupressionOccurrenceOffreMedia : voir http://api-doc.tourinsoft.com/#/questionnaire-web#api-services-medias pour les détails. Defaut : SupressionOccurrenceOffreMedia (supprime le média et les occurrences associées aux offres)
		*	@return	bool
		*/
		private function supprimerDocuments($guids,$params=null)
		{
			$TypeActions = Array('SupressionMedia','SupressionAssociationOffreMedia','SupressionOccurrenceOffreMedia') ;
			$splitsize = 50 ;
			$importMediaTypeAction = 'SupressionOccurrenceOffreMedia' ;
			if ( is_array($params) && isset($params['importMediaTypeAction']) && in_array($params['importMediaTypeAction'],$TypeActions) )
				$importMediaTypeAction = $params['importMediaTypeAction'] ;
			
			if ( ! is_array($guids) ) $guids = Array($guids) ;
			foreach ( $guids as $k => $guid )
				if ( ! $this->isGuid($guid) )
					unset($guids[$k]) ;
			
			if ( sizeof($guids) == 0 ) return false ;
			
			// On sépare en groupes de 10 images
			$groupes = array_chunk($guids,$splitsize) ;
			
			$retour = true ;
			
			$this->debug('supprimerDocuments() ['.sizeof($guids).' fichiers séparées en '.sizeof($groupes).' groupes de '.$splitsize.' fichiers]') ;
			
			$i = 1 ;
			foreach ( $groupes as $groupe )
			{
				$time_start = microtime(true) ; 
				if ( ! $this->soap_media->DeleteElement(Array(
					'client'=>$this->client,
					'utilisateurId'=>$this->utilisateurId,
					'structureId'=>$this->structureId,
					'ids'=>$groupe,
					'importMediaTypeAction' => $importMediaTypeAction,
					'questionnaireId'=>$this->questionnaireId
				)) ) return false ;
				
				$time = microtime(true) - $time_start ;
				$this->debug('supprimerDocuments() ['.$i++.'/'.sizeof($groupes).'] '.round($time,2).'s. ('.round(($time/sizeof($groupe)),2).'s. par fichier)') ;
			}
			
			$this->debug('supprimerDocuments() [terminé]') ;
			
			return $retour ;
		}
		
		/**
		*	Renvoie les fichiers contenus dans $dossierId. Attention : les fichiers sont renvoyés dans un tableau. Chaque fichier est un objet composé sur le modèle suivant :
		*		<xs:element minOccurs="0" name="elementCredit" nillable="true" type="xs:string"/>
		*		<xs:element minOccurs="0" name="elementDateCrea" type="xs:dateTime"/>
		*		<xs:element minOccurs="0" name="elementDateMaj" type="xs:dateTime"/>
		*		<xs:element minOccurs="0" name="elementHeight" nillable="true" type="xs:int"/>
		*		<xs:element minOccurs="0" name="elementId" type="ser:guid"/>
		*		<xs:element minOccurs="0" name="elementNom" nillable="true" type="xs:string"/>
		*		<xs:element minOccurs="0" name="elementSize" type="xs:int"/>
		*		<xs:element minOccurs="0" name="elementUrl" nillable="true" type="xs:string"/>
		*		<xs:element minOccurs="0" name="elementWidth" nillable="true" type="xs:int"/>
		*		Voir http://wcf.tourinsoft.com/Mediatheque/ImportMediaService.svc?xsd=xsd2 pour le détail
		*	@param	guid	$dossierId	N.C.
		*	@return	array|false
		*/
		public function getFichiers($dossierId)
		{
			
			$numPage = 0 ;
			$this->debug('getFichiers('.$dossierId.')') ;
			$ret_fichiers = Array() ;
			// La fonction ne renvoie les fichiers que par lots de 100 : on doit donc la faire tourner jusqu'à ce que le retour soit de moins de 100 fichiers.
			while ( $numPage == 0 || ( isset($fichiers) && is_array($fichiers) && sizeof($fichiers) >= 100 ) )
			{
				$this->debug('getFichiers('.$dossierId.') : getElementsByDossier('.$this->client.','.$this->questionnaireId.','.$this->structureId.','.$this->utilisateurId.','.$dossierId.','.$numPage.')') ;
				try {
					if ( $res = $this->soap_media->getElementsByDossier(Array(
						'client'=>$this->client,
						'questionnaireId'=>$this->questionnaireId,
						'structureId'=>$this->structureId,
						'utilisateurId'=>$this->utilisateurId,
						'dossierId'=>$dossierId,
						'numPage'=>$numPage++
					)) )
					{
						if ( isset($res->getElementsByDossierResult->ImportMediaElementInfo) )
						{
							$fichiers = $res->getElementsByDossierResult->ImportMediaElementInfo ;
							foreach ( $fichiers as $fic )
								$ret_fichiers[] = $fic ;
							$this->debug('getFichiers('.$dossierId.') : getElementsByDossier('.$numPage.') : '.sizeof($fichiers)) ;
						}
					}
				} catch(SoapFault $fault) { $this->error($fault) ; return false ; }
			}
			$this->debug('getFichiers('.$dossierId.') : '.sizeof($ret_fichiers).' fichiers') ;
			return $ret_fichiers ;
		}
		
		/**
		*	
		*/
		/*
		public function getArbo($dossierId,$dos=null)
		{
			$contenu = Array() ;
			
			if ( $dos !== null ) $contenu['infos'] = $dos ;
			
			// On recherche les fichiers
			$fichiers = $this->getFichiers($dossierId) ;
			foreach ( $fichiers as $fic )
			{
				if ( isset($fic->elementId) )
					$contenu['arbo'][$fic->elementId] = $fic ;
			}
			
			// On recherche les sous dossiers
			$res = $this->soap_media->getDossiers(Array(
				'client'=>$this->client,
				'questionnaireId'=>$this->questionnaireId,
				'structureId'=>$this->structureId,
				'utilisateurId'=>$this->utilisateurId,
				'dossierParentId'=>$dossierId
			)) ;
			if ( isset($res->getDossiersResult) && isset($res->getDossiersResult->ImportMediaDossier) )
			{
				$sdossiers = $res->getDossiersResult->ImportMediaDossier ;
				foreach ( $sdossiers as $dossier )
					$contenu[$dossier->dossierId] = $this->getArbo($dossier->dossierId,$dossier) ;
			}
			
			return $contenu ;
		}
		*/
		
		/**
		*	Affiche une erreur et arrête le script si debug ON
		*	@param	string|array	$fault	Message à afficher ou exception renvoyée
		*	@return	bool	N.C.
		*/
		public function error($fault)
		{
			$entete = Array() ;
			$additional_parameters = null ;
			
			if ( isset($this->mail_expediteur) && $this->check_mail($this->mail_expediteur) )
			{
				$entete['From'] = $this->mail_expediteur . '<'.$this->mail_expediteur.'>' ;
				$additional_parameters = '-f '.$this->mail_expediteur ;
			}
			
			$message = var_export($fault,true) ;
		
			$header = null ;
			foreach ( $entete as $key => $value )
				$header .= $key . ' : ' . $value . PHP_EOL ;
			
			/**
			*	Toute erreur detectée en debug entraine l'arrêt du script
			*/
			if ( $this->debug ) { $this->debug($fault) ; die() ; }
			
			if ( ! mail($this->mail_administrateur,'TisQWSMedias error',$message,$header,$additional_parameters) )
			{
				$this->debug('Impossible d\'envoyer un mail') ;
				return false ;
			}
			return true ;
		}
		
		/**
		*	Affiche un message si debug true
		*	@param	string|array	$msg	Message à afficher
		*	@param	bool	$rl	Afficher la date et un retour ligne
		*	@todo	Si on est en debug off, prévoir un envoi de tous les messages logués par mail (ou dans un fichier log ?)
		*/
		public function debug($msg,$rl=true)
		{
			$log = null ;
			
			if ( $rl ) $log .= "\n".date('Y-m-d H:i:s').' | ' ;
			if ( is_array($msg) ) $log .= var_export($msg,true) ;
			else $log .= $msg ;
			
			if ( $this->debug )	echo $log ;
			// @todo : envoyer le $log par mail si debug off
		}
		
		/**
		*	Vérifie que $guid est bien un GUID valide
		*	@param	guid	$guid N.C.
		*	@return	bool	N.C.
		*/
		private function isGuid($guid)
		{
			if ( ! is_string($guid) ) return false ;
			return preg_match('/^[A-Za-z0-9]{8}-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}-[A-Za-z0-9]{12}?$/', $guid) ;
		}
	
		/**
		*	Vérifie que $mail est un mail valide
		*	@param	string	$mail	N.C.
		*	@return	bool	N.C.
		*/
		private function check_mail($mail)
		{
			return preg_match('#^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]{2,}[.][a-zA-Z]{2,3}$#i',$mail) ;
		}
	
	}
	