<?php

namespace AW2MW;

class Infobox
{
    const CONSTRUCTION_EVENTS_TYPE = [
        'Construction', 'Rénovation', 'Transformation', 'Démolition', 'Extension', 'Ravalement',
    ];

    public static function getNumberedInfo(array $infobox)
    {
        $j = $k = $l = 0;
        $intro = '';
        foreach ($infobox as $i => $info) {
            if (in_array($info['type'], self::CONSTRUCTION_EVENTS_TYPE)) {
                $j++;
                if (substr($info['date']['start'], 5) == '00-00') {
                    $info['date']['start'] = substr($info['date']['start'], 0, 4);
                }
                if (substr($info['date']['end'], 5) == '00-00') {
                    $info['date']['end'] = substr($info['date']['end'], 0, 4);
                }
                if ($info['date']['start'] == '0000') {
                    $info['date']['start'] = '';
                }
                if ($info['date']['end'] == '0000') {
                    $info['date']['end'] = '';
                }
                $intro .= '|date'.$j.'_afficher = '.$info['date']['pretty'].PHP_EOL;
                $intro .= '|date'.$j.'_début = '.$info['date']['start'].PHP_EOL;
                $intro .= '|date'.$j.'_fin = '.$info['date']['end'].PHP_EOL;
                if ($i > 0 && $info['structure'] == $infobox[$i - 1]['structure']) {
                    $info['structure'] = '';
                }
                $intro .= '|structure'.$j.' = '.$info['structure'].PHP_EOL;
                $intro .= '|type'.$j.' = '.strtolower($info['type']).PHP_EOL;
                $intro .= '|courant'.$j.' = '.strtolower($info['courant']).PHP_EOL;
                foreach ($info['people'] as $job => $name) {
                    $intro .= '|'.$job.$j.' = '.$name.PHP_EOL;
                }
            }
            if (isset($info['ismh'])) {
                $k++;
                $intro .= '|ismh'.$k.'='.$info['date']['pretty'].PHP_EOL;
            }
            if (isset($info['mh'])) {
                $l++;
                $intro .= '|mh'.$l.'='.$info['date']['pretty'].PHP_EOL;
            }
        }

        return $intro;
    }

    private function getCourants($id)
    {
        $e = new \archiEvenement();
        $rep = $e->connexionBdd->requete(
            'SELECT  cA.idCourantArchitectural, cA.nom
			FROM _evenementCourantArchitectural _eA
			LEFT JOIN courantArchitectural cA  ON cA.idCourantArchitectural  = _eA.idCourantArchitectural
			WHERE _eA.idEvenement='.$id.'
			ORDER BY cA.nom ASC'
        );
        $results = [];
        if (mysql_num_rows($rep) > 0) {
            while ($res = mysql_fetch_object($rep)) {
                $results[] = $res->nom;
            }
        }

        return implode(';', $results);
    }

    public function getInfoboxInfo(array $event)
    {
        $info = [
            'type'      => '',
            'structure' => '',
            'date'      => '',
            'people'    => [],
        ];

        $info['type'] = str_replace('(Nouveautés)', '', $event['nomTypeEvenement']);
        $info['structure'] = $event['nomTypeStructure'];
        $info['date'] = [
            'pretty' => self::convertDate($event['dateDebut'], $event['dateFin'], $event['isDateDebutEnviron']),
            'start'  => $event['dateDebut'],
            'end'    => $event['dateFin'],
        ];
        if ($event['ISMH'] > 0) {
            $info['ismh'] = true;
        }
        if ($event['MH'] > 0) {
            $info['mh'] = true;
        }
        $info['courant'] = self::getCourants($event['idEvenement']);

        $people = Person::getPeople($event['idEvenement']);
        foreach ($people as $person) {
            if (isset($info['people'][$person->metier]) && !empty($info['people'][$person->metier])) {
                $info['people'][$person->metier] .= ';'.$person->prenom.' '.$person->nom;
            } else {
                $info['people'][$person->metier] = $person->prenom.' '.$person->nom;
            }
        }

        return $info;
    }

    public static function convertDate($startDate, $endDate, $approximately)
    {
        $e = new \archiEvenement();
        $date = '';
        if ($approximately == '1') {
            $date .= 'environ ';
        }
        if (substr($startDate, 5) == '00-00') {
            $datetime = substr($startDate, 0, 4);
        } else {
            $datetime = $startDate;
        }
        if ($startDate != '0000-00-00') {
            $date .= $e->date->toFrenchAffichage($datetime);
        }
        if ($endDate != '0000-00-00') {
            if (strlen($e->date->toFrench($endDate)) <= 4) {
                $date .= ' à '.$e->date->toFrenchAffichage($endDate);
            } else {
                $date .= ' au '.$e->date->toFrenchAffichage($endDate);
            }
        }

        return $date;
    }
}
