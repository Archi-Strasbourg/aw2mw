<?php

namespace AW2MW;

class Source
{
    public static function getSourceType($id)
    {
        $s = new \ArchiSource();
        $reqTypeSource = '
            SELECT tS.nom
            FROM source s
            LEFT JOIN typeSource tS USING (idTypeSource)
            WHERE idSource = '.mysql_real_escape_string($id).' LIMIT 1
        ';
        $resTypeSource = $s->connexionBdd->requete($reqTypeSource);
        $typeSource = mysql_fetch_assoc($resTypeSource);

        return $typeSource['nom'];
    }

    public static function getSourceName($id, $addCategory = true)
    {
        $s = new \ArchiSource();
        $origPageName = self::escapeSourceName($s->getSourceLibelle($id));
        if (empty($origPageName)) {
            throw new \Exception('Empty source name (ID '.$id.')');
        }

        if ($addCategory) {
            return $origPageName.' ('.self::getSourceType($id).')';
        } else {
            return $origPageName;
        }
    }

    /**
     * @param string $name
     */
    public static function escapeSourceName($name)
    {
        $name = stripslashes($name);
        $name = str_replace('"', '', $name);
        $name = urldecode($name);
        $name = trim($name);

        return $name;
    }
}
