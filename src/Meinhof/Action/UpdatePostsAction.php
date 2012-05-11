<?php

namespace Meinhof\Action;

use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Templating\EngineInterface;

use Meinhof\Helper\UrlHelperInterface;
use Meinhof\Model\Post\PostInterface;
use Meinhof\Model\Site\SiteInterface;
use Meinhof\Exporter\ExporterInterface;

class UpdatePostsAction extends OutputAction
{
    protected $site;
    protected $exporter;
    protected $output;

    public function __construct(SiteInterface $site, 
        ExporterInterface $exporter, OutputInterface $output=null)
    {
        $this->site = $site;
        $this->exporter = $exporter;
        $this->output = $output;
    }

    protected function getOutput()
    {
        return $this->output;
    }

    public function take()
    {
        $posts = $this->site->getPosts();
        $this->writeOutputLine(sprintf("updating %d posts...", count($posts)), 2);

        foreach($posts as $post){
            if(!$post instanceof PostInterface){
                throw new \RuntimeException("Site returned invalid post.");
            }
            $this->exporter->exportPost($post, $this->site);
            $this->writeOutput(".", 1);
        }
        $this->writeOutputLine("", 1);
        $this->writeOutputLine("done", 2);
    }
}