TisQWSMedia
===========

Classe d'utilisation du webservice ImportMediaService de Tourinsoft V5

<ul>
	<li>Documentation en ligne : <a href="http://cdt.allier-auvergne-tourisme.com/TisQWSMedia/docs">http://cdt.allier-auvergne-tourisme.com/TisQWSMedia/docs</a></li>
	<li>Pierre Granger p.granger at allier-tourisme.net</li>
	<li>licence MIT</li>
	<li>Site professionnel : <a hre"http://www.allier-auvergne-tourisme.com">http://www.allier-auvergne-tourisme.com</a></li>
	<li>Page personnelle : <a href="http://www.pierre-granger.fr">http://www.pierre-granger.fr</a></li>
</ul>

Cette classe est fournie en l'état, sans garantie de fonctionnement, sous licence MIT : vous pouvez la réutiliser et la modifier à votre guise, sans nécessairement fournir votre propre code source (même si toute participation est la bienvenue).

L'objectif est de pouvoir automatiser l'envoi massif de photos (imports, mises à jour régulières, passerelles...)

Pour commencer à l'utiliser, vous pouvez ouvrir le fichier exemple.php, qui montre comment :
<ul>
	<li>Rechercher un dossier dont le nom sera l'identifiant de l'offre à partir d'un dossier racine défini (ex: GDF/HLOAUV...)</li>
	<li>Créer le dossier s'il n'existe pas</li>
	<li>Vider le dossier s'il existe déjà</li>
	<li>Envoyer les photos dans le dossier trouvé/créé</li>
	<li>Associer toutes les photos envoyées à une offre</li>
</ul>

En préalable à son utilisation, vous devrez créer un Questionnaire Web sur Tourinsoft :
<ul>
	<li>Gen. Web > Questionnaire Web</li>
	<li>Le questionnaire devra être fait sur le bordereau correspondant à vos offres (HLO...)</li>
	<li>Le questionnaire créé devra simplement contenir X fois le champ [Photo] (Ex : [Photo1][Photo2][Photo3]... (selon le nombre max. de photo que vous comptez envoyer pour chaque offre). Pas besoin d'autre chose (pas de bouton valider ni de mise en page)</li>
	<li>Il devra être publié en tant que WebService (Gen. Web > Questionnaire Web > Gestion de la publication > [Votre questionnaire] > Type de publication souhaitée : Web service)</li>
	<li>Vous trouverez la plupart des informations demandées dans le fichier exemple.php (questionnaireId, structureId, client...) en bas de cette publication. Se réféfer à la documentation en ligne de la classe TisQWSMedia pour plus de détails : <a href="http://cdt.allier-auvergne-tourisme.com/TisQWSMedia/docs/classes/TisQWSMedias.html#properties">http://cdt.allier-auvergne-tourisme.com/TisQWSMedia/docs/classes/TisQWSMedias.html#properties</a></li>
</ul>