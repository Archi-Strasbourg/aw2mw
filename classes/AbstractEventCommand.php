<?php

namespace AW2MW;

use Mediawiki\Api;

abstract class AbstractEventCommand extends ExportCommand
{
    /**
     * @param string $pageName
     */
    protected function exportEvent($id, $section, $pageName)
    {
        $req = 'SELECT idHistoriqueEvenement
                FROM historiqueEvenement
                WHERE idEvenement='.$id.' order by dateCreationEvenement ASC';
        $res = $this->e->connexionBdd->requete($req);

        while ($fetch = mysql_fetch_assoc($res)) {
            $sql = 'SELECT  hE.idEvenement,
                    hE.titre, hE.idSource,
                    hE.idTypeStructure,
                    hE.idTypeEvenement,
                    hE.description,
                    hE.dateDebut,
                    hE.dateFin,
                    hE.dateDebut,
                    hE.dateFin,
                    tE.nom AS nomTypeEvenement,
                    tS.nom AS nomTypeStructure,
                    s.nom AS nomSource,
                    u.nom AS nomUtilisateur,
                    u.prenom as prenomUtilisateur,
                    tE.groupe,
                    hE.ISMH ,
                    hE.MH,
                    date_format(hE.dateCreationEvenement,"'._('%e/%m/%Y à %kh%i').'") as dateCreationEvenement,
                    hE.isDateDebutEnviron as isDateDebutEnviron,
                    u.idUtilisateur as idUtilisateur,
                    hE.numeroArchive as numeroArchive

                    FROM historiqueEvenement hE
                    LEFT JOIN source s      ON s.idSource = hE.idSource
                    LEFT JOIN typeStructure tS  ON tS.idTypeStructure = hE.idTypeStructure
                    LEFT JOIN typeEvenement tE  ON tE.idTypeEvenement = hE.idTypeEvenement
                    LEFT JOIN utilisateur u     ON u.idUtilisateur = hE.idUtilisateur
                    WHERE hE.idHistoriqueEvenement = '.mysql_real_escape_string($fetch['idHistoriqueEvenement']).'
            ORDER BY hE.idHistoriqueEvenement DESC';

            $event = mysql_fetch_assoc($this->e->connexionBdd->requete($sql));

            $user = $this->u->getArrayInfosFromUtilisateur($event['idUtilisateur']);

            //Login as user
            $this->loginManager->login($user['prenom'].' '.$user['nom']);

            $content = '';
            $date = $this->convertDate($event['dateDebut'], $event['dateFin'], $event['isDateDebutEnviron']);

            if (!empty($event['titre'])) {
                $title = $event['titre'];
            } else {
                $title = str_replace('(Nouveautés)', '', $event['nomTypeEvenement']);
            }

            if ($event['idSource'] > 0) {
                $sourceName = $this->source->getSourceName($event['idSource']);
                $title .= '<ref>{{source|'.$sourceName.'}}</ref>';
            }
            if (!empty($event['numeroArchive'])) {
                $sourceName = $this->source->getSourceName(24);
                $title .= '<ref>{{source|'.$sourceName.'}} - Cote '.
                    $event['numeroArchive'].'</ref>';
            }

            $title = ucfirst(stripslashes($title));
            $content .= '== '.$title.' =='.PHP_EOL;

            $people = [];
            foreach ($this->getPeople($id) as $person) {
                if (isset($people[$person->metier]) && !empty($people[$person->metier])) {
                    $people[$person->metier] .= ';'.$person->prenom.' '.$person->nom;
                } else {
                    $people[$person->metier] = $person->prenom.' '.$person->nom;
                }
            }
            $content .= '{{Infobox actualité'.PHP_EOL.
                '|date = '.$date.PHP_EOL;
            foreach ($people as $job => $person) {
                $content .= '|'.$job.' = '.$person.PHP_EOL;
            }
            if ($event['ISMH'] > 0) {
                '|ismh = oui'.PHP_EOL;
            }
            if ($event['MH'] > 0) {
                '|mh = oui'.PHP_EOL;
            }
            $content .= '}}'.PHP_EOL;

            $html = $this->convertHtml(
                (string) $this->bbCode->convertToDisplay(['text' => $event['description']])
            );

            $content .= trim($html).PHP_EOL.PHP_EOL;
            $this->api->postRequest(
                new Api\SimpleRequest(
                    'edit',
                    [
                        'title'   => $pageName,
                        'md5'     => md5($content),
                        'text'    => $content,
                        'section' => $section + 1,
                        'bot'     => true,
                        'summary' => 'Révision du '.$event['dateCreationEvenement'].' importée depuis Archi-Wiki',
                        'token'   => $this->api->getToken(),
                    ]
                )
            );
            $this->sections[$section + 1] = $content;

            $this->exportEventImages($id, $section, $pageName);
        }
    }

    protected function getPeople($id)
    {
        $rep = $this->e->connexionBdd->requete('
                SELECT  p.idPersonne, m.nom as metier, p.nom, p.prenom
                FROM _evenementPersonne _eP
                LEFT JOIN personne p ON p.idPersonne = _eP.idPersonne
                LEFT JOIN metier m ON m.idMetier = p.idMetier
                WHERE _eP.idEvenement='.mysql_real_escape_string($id).'
                ORDER BY p.nom DESC');
        $people = [];
        while ($res = mysql_fetch_object($rep)) {
            $people[] = $res;
        }

        return $people;
    }

    /**
     * @param int    $section
     * @param string $pageName
     */
    protected function exportEventImages($id, $section, $pageName)
    {
        $reqImages = "
            SELECT hi1.idImage, hi1.description
            FROM _evenementImage ei
            LEFT JOIN historiqueImage hi1 ON hi1.idImage = ei.idImage
            LEFT JOIN historiqueImage hi2 ON hi2.idImage = hi1.idImage
            WHERE ei.idEvenement = '".mysql_real_escape_string($id)."'
            GROUP BY hi1.idImage ,  hi1.idHistoriqueImage
            HAVING hi1.idHistoriqueImage = max(hi2.idHistoriqueImage)
            ORDER BY ei.position, hi1.idHistoriqueImage
            ";

        $resImages = $this->i->connexionBdd->requete($reqImages);
        $images = [];
        while ($fetchImages = mysql_fetch_assoc($resImages)) {
            $images[] = $fetchImages;
        }

        if (!empty($images)) {
            $this->sections[$section + 1] .= $this->createGallery($images);
        }
        $this->api->postRequest(
            new Api\SimpleRequest(
                'edit',
                [
                    'title'   => $pageName,
                    'md5'     => md5($this->sections[$section + 1]),
                    'text'    => $this->sections[$section + 1],
                    'section' => $section + 1,
                    'bot'     => true,
                    'summary' => 'Images importées depuis Archi-Wiki',
                    'token'   => $this->api->getToken(),
                ]
            )
        );
    }
}
