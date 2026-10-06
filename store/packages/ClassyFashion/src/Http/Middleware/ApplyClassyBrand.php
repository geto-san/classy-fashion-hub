<?php

namespace ClassyFashion\Http\Middleware;

use ClassyFashion\Support\Brand;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the single Classy Fashion Hub brand to every HTML page.
 *
 * Covers shop and admin for all users by rewriting Bagisto fallback marks
 * (generator, powered-by, footer copyright, stock logo and favicon URLs)
 * after the controller has rendered. Non-HTML responses pass through.
 */
class ApplyClassyBrand
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $contentType = (string) $response->headers->get('Content-Type', '');

        if (
            $contentType !== ''
            && ! str_contains($contentType, 'text/html')
        ) {
            return $response;
        }

        if (! method_exists($response, 'getContent') || ! method_exists($response, 'setContent')) {
            return $response;
        }

        $content = $response->getContent();

        if (! is_string($content) || $content === '') {
            return $response;
        }

        $response->setContent(Brand::apply($content));

        return $response;
    }
}
