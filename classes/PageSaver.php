<?php

namespace AW2MW;

use Mediawiki\DataModel;

class PageSaver
{
    private $services;
    private $revisionSaver;

    public function __construct($services)
    {
        $this->services = $services;
        $this->revisionSaver = $this->services->newRevisionSaver();
    }

    /**
     * @param string $note
     * @param string $pageName
     * @param string $content
     */
    public function savePage($pageName, $content, $note)
    {
        $this->revisionSaver->save(
            new DataModel\Revision(
                new DataModel\Content($content),
                new DataModel\PageIdentifier(new DataModel\Title($pageName))
            ),
            new DataModel\EditInfo($note, true, true)
        );
    }

    /**
     * @param string $pageName
     */
    public function deletePage($pageName)
    {
        //Delete article if it already exists
        $page = $this->services->newPageGetter()->getFromTitle($pageName);
        if ($page->getPageIdentifier()->getId() > 0) {
            $this->services->newPageDeleter()->delete($page);
        }
    }
}
