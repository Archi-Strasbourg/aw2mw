<?php

namespace AW2MW;

class Source
{
    private $s;

    public function __construct()
    {
        $this->s = new \ArchiSource();
    }

    public function getSourceType($id)
    {
        $reqTypeSource = '
            SELECT tS.nom
            FROM source s
            LEFT JOIN typeSource tS USING (idTypeSource)
            WHERE idSource = '.mysql_real_escape_string($id).' LIMIT 1
        ';
        $resTypeSource = $this->s->connexionBdd->requete($reqTypeSource);
        $typeSource = mysql_fetch_assoc($resTypeSource);

        return $typeSource['nom'];
    }

    public function getSourceName($id)
    {
        $origPageName = $this->escapeSourceName($this->s->getSourceLibelle($id));
        if (empty($origPageName)) {
            throw new Exception('Empty source name (ID '.$id.')');
        }

        return $origPageName.' ('.$this->getSourceType($id).')';
    }

    /**
     * @param string $name
     */
    public function escapeSourceName($name)
    {
        $name = stripslashes($name);
        $name = str_replace('"', '', $name);
        $name = urldecode($name);
        $name = trim($name);

        return $name;
    }
}
