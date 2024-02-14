<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Routing;

use TYPO3\CMS\Core\Routing\Aspect\StaticMappableAspectInterface;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Routing\Route;
use TYPO3\CMS\Core\Routing\RouteCollection;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Extbase\Routing\ExtbasePluginEnhancer;

class RestApiEnhancer extends ExtbasePluginEnhancer
{
    protected array $queryMapping = [];

    protected string $routePrefix;

    public function __construct(array $configuration)
    {
        parent::__construct($configuration);
        $this->routePrefix = $this->configuration['routePrefix'];
    }

    /**
     * {@inheritdoc}
     */
    public function enhanceForGeneration(RouteCollection $collection, array $originalParameters): void
    {
        if (!is_array($originalParameters[$this->namespace] ?? null)) {
            return;
        }
        // apply default controller and action names if not set in parameters
        if (!$this->hasControllerActionValues($originalParameters[$this->namespace])
            && !empty($this->configuration['defaultController'])
        ) {
            $this->applyControllerActionValues(
                $this->configuration['defaultController'],
                $originalParameters[$this->namespace],
                true
            );
        }

        $i = 0;
        /** @var Route $defaultPageRoute */
        $defaultPageRoute = $collection->get('default');
        foreach ($this->routesOfPlugin as $configuration) {
            $variant = $this->getVariant($defaultPageRoute, $configuration);
            // The enhancer tells us: This given route does not match the parameters
            if (!$this->verifyRequiredParameters($variant, $originalParameters)) {
                continue;
            }
            $parameters = $originalParameters;
            unset($parameters[$this->namespace]['action']);
            unset($parameters[$this->namespace]['controller']);
            $compiledRoute = $variant->compile();
            $deflatedQueryParams = [];
            foreach ($variant->getOptions()['_queryMapping'] as $queryParam => $actionParamName) {
                // store all found possible query parameters to remove them during inflation from the parameters to avoid
                // a cHash being generated for them
                $this->queryMapping[$queryParam] = $actionParamName;
                $deflatedQueryParams[$queryParam] = $parameters[$this->namespace][$actionParamName];
                unset($parameters[$this->namespace][$actionParamName]);
            }
            // contains all given parameters, even if not used as variables in route
            $deflatedParameters = $this->deflateParameters($variant, $parameters) + $deflatedQueryParams;
            $variables = array_flip($compiledRoute->getPathVariables());
            $mergedParams = array_replace($variant->getDefaults(), $deflatedParameters);
            // all params must be given, otherwise we exclude this variant
            // (it is allowed that $variables is empty - in this case variables are
            // "given" implicitly through controller-action pair in `_controller`)
            if (array_diff_key($variables, $mergedParams)) {
                continue;
            }
            $variant->addOptions(['deflatedParameters' => $deflatedParameters]);
            $collection->add($this->namespace . '_' . $i++, $variant);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getVariant(Route $defaultPageRoute, array $configuration): Route
    {
        $arguments = $configuration['_arguments'] ?? [];
        $queryMapping = $configuration['_queryMapping'] ?? [];
        unset($configuration['_arguments'], $configuration['_queryMapping']);

        $variableProcessor = $this->getVariableProcessor();
        $routePath = $this->routePrefix . $this->modifyRoutePath($configuration['routePath']);
        $routePath = $variableProcessor->deflateRoutePath($routePath, $this->namespace, $arguments);
        unset($configuration['routePath']);
        $options = array_merge($defaultPageRoute->getOptions(), ['_enhancer' => $this, 'utf8' => true, '_arguments' => $arguments, '_queryMapping' => $queryMapping]);
        $route = new Route(rtrim($defaultPageRoute->getPath(), '/') . '/' . ltrim($routePath, '/'), [], [], $options);

        $defaults = array_merge_recursive(
            $defaultPageRoute->getDefaults(),
            $variableProcessor->deflateKeys($this->configuration['defaults'] ?? [], $this->namespace, $arguments)
        );
        // only keep `defaults` that are actually used in `routePath`
        $defaults = $this->filterValuesByPathVariables(
            $route,
            $defaults
        );
        // apply '_controller' to route defaults
        $defaults = array_merge_recursive(
            $defaults,
            array_intersect_key($configuration, ['_controller' => true])
        );
        $route->setDefaults($defaults);
        $this->applyRouteAspects($route, $this->aspects ?? [], $this->namespace);
        $this->applyRequirements($route, $this->configuration['requirements'] ?? [], $this->namespace);
        return $route;
    }

    public function inflateParameters(array $parameters, array $internals = []): array
    {
        // remove expected query parameters
        $parameters = array_filter($parameters, function (string $queryParamName) { return !isset($this->queryMapping[$queryParamName]); }, ARRAY_FILTER_USE_KEY);
        return parent::inflateParameters($parameters, $internals);
    }

    /**
     * {@inheritdoc}
     */
    public function buildResult(Route $route, array $results, array $remainingQueryParameters = []): PageArguments
    {
        $variableProcessor = $this->getVariableProcessor();
        // determine those parameters that have been processed
        $parameters = array_intersect_key(
            $results,
            array_flip($route->compile()->getPathVariables()) + $route->getOptions()['_queryMapping']
        );
        // strip of those that where not processed (internals like _route, etc.)
        $internals = array_diff_key($results, $parameters);
        $matchedVariableNames = array_keys($parameters);

        $staticMappers = $route->filterAspects([StaticMappableAspectInterface::class], $matchedVariableNames);
        $dynamicCandidates = array_diff_key($parameters, $staticMappers);

        // all route arguments
        $routeArguments = $this->inflateParameters($parameters, $internals);
        // dynamic arguments, that don't have a static mapper
        $dynamicArguments = $variableProcessor
            ->inflateNamespaceParameters($dynamicCandidates, $this->namespace);
        // route arguments, that don't appear in dynamic arguments
        $staticArguments = ArrayUtility::arrayDiffKeyRecursive($routeArguments, $dynamicArguments);

        foreach ($remainingQueryParameters as $name => $value) {
            if (isset($route->getOptions()['_queryMapping'][$name])) {
                $routeArguments[$this->namespace][$route->getOptions()['_queryMapping'][$name]] = $value === '' ? '1' : $value;
            }
        }

        $page = $route->getOption('_page');
        $pageId = (int)(isset($page['t3ver_oid']) && $page['t3ver_oid'] > 0 ? $page['t3ver_oid'] : $page['uid']);
        $pageId = (int)($page['l10n_parent'] > 0 ? $page['l10n_parent'] : $pageId);
        // See PageSlugCandidateProvider where this is added.
        if ($page['MPvar'] ?? '') {
            $routeArguments['MP'] = $page['MPvar'];
        }
        $type = $this->resolveType($route, $remainingQueryParameters);
        return new PageArguments($pageId, $type, $routeArguments, $staticArguments, $remainingQueryParameters);
    }
}
