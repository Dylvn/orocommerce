<?php

namespace Oro\Bundle\RedirectBundle\EventListener;

use Oro\Bundle\RedirectBundle\Routing\MatchedUrlDecisionMaker;
use Oro\Bundle\RedirectBundle\Routing\SlugRedirectMatcher;
use Oro\Component\Routing\UrlUtil;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Catches the 404 exceptions from the router and tries to find the correct redirects for them.
 */
class RedirectExceptionListener
{
    /** @var SlugRedirectMatcher */
    private $redirectMatcher;

    /** @var MatchedUrlDecisionMaker */
    private $matchedUrlDecisionMaker;

    /**
     * @param SlugRedirectMatcher     $redirectMatcher
     * @param MatchedUrlDecisionMaker $matchedUrlDecisionMaker
     */
    public function __construct(
        SlugRedirectMatcher $redirectMatcher,
        MatchedUrlDecisionMaker $matchedUrlDecisionMaker
    ) {
        $this->redirectMatcher = $redirectMatcher;
        $this->matchedUrlDecisionMaker = $matchedUrlDecisionMaker;
    }

    /**
     * @param GetResponseForExceptionEvent $event
     */
    public function onKernelException(GetResponseForExceptionEvent $event): void
    {
        if (!$this->isRedirectRequired($event)) {
            return;
        }

        $request = $event->getRequest();
        $attributes = $this->redirectMatcher->match($request->getPathInfo());
        if ($attributes) {
            $event->setResponse(new RedirectResponse(
                UrlUtil::getAbsolutePath($attributes['pathInfo'], $request->getBaseUrl()),
                $attributes['statusCode']
            ));
        }
    }

    /**
     * @param GetResponseForExceptionEvent $event
     *
     * @return bool
     */
    private function isRedirectRequired(GetResponseForExceptionEvent $event): bool
    {
        return
            $event->isMasterRequest()
            && !$event->hasResponse()
            && $event->getThrowable() instanceof NotFoundHttpException
            && $this->matchedUrlDecisionMaker->matches($event->getRequest()->getPathInfo());
    }
}
