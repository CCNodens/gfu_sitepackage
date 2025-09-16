<?php

namespace Vancado\VncNews\LinkHandler;

use Psr\Http\Message\ServerRequestInterface;

class NewsImageLinkHandler implements LinkHandlerInterface
{
    public function render(ServerRequestInterface $request): string
    {
        $this->pageRenderer->loadJavaScriptModule('@vnc_news/Resources/Public/Js/Backend/vnc-news-image-linkhandler.js');
        $this->view->assign('image', $this->configuration['image']);
        $this->view->assign('alt', $this->configuration['alt']);

        return $this->view->render('LinkBrowser/GitHub');
    }
}