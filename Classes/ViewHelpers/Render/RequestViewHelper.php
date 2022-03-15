<?php
namespace FluidTYPO3\Vhs\ViewHelpers\Render;

/*
 * This file is part of the FluidTYPO3/Vhs project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Dispatcher;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

/**
 * ### Render: Request
 *
 * Renders a sub-request to the desired Extension, Plugin,
 * Controller and action with the desired arguments.
 *
 * Note: arguments must not be wrapped with the prefix used
 * in GET/POST parameters but must be provided as if the
 * arguments were sent directly to the Controller action.
 */
class RequestViewHelper extends AbstractRenderViewHelper
{
    use CompileWithRenderStatic;

    /**
     * @var string
     */
    protected static $requestType = Request::class;

    /**
     * @var string
     */
    protected static $responseType = HtmlResponse::class;

    /**
     * @return void
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('action', 'string', 'Controller action to call in request');
        $this->registerArgument('controller', 'string', 'Controller name to call in request');
        $this->registerArgument('extensionName', 'string', 'Extension name scope to use in request');
        $this->registerArgument('controllerClassName', 'string', 'Fully qualified class name of the controller', 1);
        $this->registerArgument('pluginName', 'string', 'Plugin name scope to use in request');
        $this->registerArgument('arguments', 'array', 'Arguments to use in request');
    }

    /**
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     * @return string
     * @throws \Exception
     */
    public static function renderStatic(
        array $arguments,
        \Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ) {
        $action = $arguments['action'];
        $controller = $arguments['controller'];
        $extensionName = $arguments['extensionName'];
        $pluginName = $arguments['pluginName'];
        $controllerClassName = $arguments['controllerClassName'];
        $requestArguments = is_array($arguments['arguments']) ? $arguments['arguments'] : [];
        $configurationManager = static::getConfigurationManager();
        $contentObjectBackup = $configurationManager->getContentObject();
        $request = $renderingContext->getControllerContext()->getRequest();
        $configurationBackup = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
            $request->getControllerExtensionName(),
            $request->getPluginName()
        );

        $temporaryContentObject = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $extbaseAttribute = new ExtbaseRequestParameters();
        $extbaseAttribute->setPluginName($pluginName);
        $extbaseAttribute->setControllerExtensionName($extensionName);
        $extbaseAttribute->setControllerAliasToClassNameMapping([$controller => $controllerClassName]);
        $extbaseAttribute->setControllerName( $controller);
        $extbaseAttribute->setControllerActionName($action);
        $extbaseAttribute->setFormat('html');

        $serverRequest = $GLOBALS['TYPO3_REQUEST'] ?? new ServerRequest();
        $request = new Request($serverRequest->withAttribute('extbase', $extbaseAttribute));

        if (!empty($requestArguments)) {
            $request->setArguments($requestArguments);
        }

        try {
            $configurationManager->setContentObject($temporaryContentObject);
            $configurationManager->setConfiguration(
                $configurationManager->getConfiguration(
                    ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
                    $extensionName,
                    $pluginName
                )
            );
            $response = static::getDispatcher()->dispatch($request);
            $configurationManager->setContentObject($contentObjectBackup);
            if (true === isset($configurationBackup)) {
                $configurationManager->setConfiguration($configurationBackup);
            }
            return $response->getBody();
        } catch (\Exception $error) {
            if (false === (boolean) $arguments['graceful']) {
                throw $error;
            }
            if (false === empty($arguments['onError'])) {
                return sprintf($arguments['onError'], [$error->getMessage()], $error->getCode());
            }
            return $error->getMessage() . ' (' . $error->getCode() . ')';
        }
    }

    /**
     * @return Dispatcher
     */
    protected static function getDispatcher()
    {
        return static::getObjectManager()->get(Dispatcher::class);
    }

    /**
     * @return ConfigurationManagerInterface
     */
    protected static function getConfigurationManager()
    {
        return static::getObjectManager()->get(ConfigurationManagerInterface::class);
    }
}
