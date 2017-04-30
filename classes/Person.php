<?php

namespace AW2MW;

class Person
{
    public static function getPeople($id)
    {
        $e = new \archiEvenement();
        $rep = $e->connexionBdd->requete('
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
}
