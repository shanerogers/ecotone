<?php
declare(strict_types=1);

namespace Ecotone\Messaging\Config\Annotation;

use Doctrine\Common\Annotations\Reader;
use http\Env;
use InvalidArgumentException;
use function json_decode;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Ecotone\Messaging\Annotation\Environment;
use Ecotone\Messaging\Config\ConfigurationException;
use Ecotone\Messaging\Handler\AnnotationParser;
use Ecotone\Messaging\Handler\TypeResolver;
use Ecotone\Messaging\MessagingException;
use SplFileInfo;

/**
 * Class FileSystemAnnotationRegistrationService
 * @package Ecotone\Messaging\Config\Annotation
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
class FileSystemAnnotationRegistrationService implements AnnotationRegistrationService, AnnotationParser
{
    const FRAMEWORK_NAMESPACE = 'Ecotone';
    private const FILE_EXTENSION = '.php';

    /**
     * @var Reader
     */
    private $annotationReader;
    /**
     * @var string[]
     */
    private $registeredClasses;
    /**
     * @var array
     */
    private $bannedEnvironmentClassMethods = [];
    /**
     * @var string[]
     */
    private $cachedMethodAnnotations = [];

    /**
     * @var object[][]
     */
    private $cachedClassAnnotations = [];

    /**
     * FileSystemAnnotationRegistrationService constructor.
     * @param Reader $annotationReader
     * @param AutoloadNamespaceParser $autoloadNamespaceParser
     * @param string $rootProjectDir
     * @param array $namespaces to autoload, if loadSrc is set then no need to provide src namespaces
     * @param string $environmentName
     * @param string $catalogToLoad
     * @throws ConfigurationException
     * @throws MessagingException
     * @throws \Ecotone\Messaging\Support\InvalidArgumentException
     */
    public function __construct(Reader $annotationReader, AutoloadNamespaceParser $autoloadNamespaceParser, string $rootProjectDir, array $namespaces, string $environmentName, string $catalogToLoad)
    {
        $this->annotationReader = $annotationReader;
        $this->init($rootProjectDir, array_unique($namespaces), $catalogToLoad, $autoloadNamespaceParser);

        $classNamesWithEnvironment = $this->getAllClassesWithAnnotation(Environment::class);
        foreach ($classNamesWithEnvironment as $classNameWithEnvironment) {
            /** @var Environment $environment */
            $environment = $this->getAnnotationForClass($classNameWithEnvironment, Environment::class);

            if (!in_array($environmentName, $environment->names)) {
                $key = array_search($classNameWithEnvironment, $this->registeredClasses);
                if ($key) {
                    unset($this->registeredClasses[$key]);
                    $this->registeredClasses = array_values($this->registeredClasses);
                }
            }
        }


        foreach ($this->registeredClasses as $className) {
            foreach (get_class_methods($className) as $method) {
                $classAnnotations = array_values(array_filter($methodAnnotations = array_map(function(object $annotation){
                    if ($annotation instanceof Environment) {
                        return $annotation;
                    }

                    return null;
                }, $this->getCachedAnnotationsForClass($className))));
                $methodAnnotations = array_values(array_filter(array_map(function(object $annotation){
                    if ($annotation instanceof Environment) {
                        return $annotation;
                    }

                    return null;
                }, $this->getCachedMethodAnnotations($className, $method))));

                if ($methodAnnotations) {
                    if (!in_array($environmentName, $methodAnnotations[0]->names)) {
                        $this->bannedEnvironmentClassMethods[$className][$method] = true;
                    }
                }else if ($classAnnotations) {
                    if (!in_array($environmentName, $classAnnotations[0]->names)) {
                        $this->bannedEnvironmentClassMethods[$className][$method] = true;
                    }
                }
            }
        }
    }

    /**
     * @param string $rootProjectDir
     * @param array $namespacesToUse
     * @param string $catalogToLoad
     * @throws ConfigurationException
     * @throws MessagingException
     */
    private function init(string $rootProjectDir, array $namespacesToUse, string $catalogToLoad, AutoloadNamespaceParser $autoloadNamespaceParser)
    {
        $classes = [];
        $composerPath = $rootProjectDir . "/composer.json";
        if ($catalogToLoad && !file_exists($composerPath)) {
            throw new InvalidArgumentException("Can't load src, composer.json not found in {$composerPath}");
        }
        if ($catalogToLoad) {
            $composerJsonDecoded = json_decode(file_get_contents($composerPath), true);

            if (isset($composerJsonDecoded['autoload'])) {
                $namespacesToUse = array_merge($namespacesToUse, $autoloadNamespaceParser->getNamespacesForGivenCatalog($composerJsonDecoded['autoload'], $catalogToLoad));
            }
            if (isset($composerJsonDecoded['autoload-dev'])) {
                $namespacesToUse = array_merge($namespacesToUse, $autoloadNamespaceParser->getNamespacesForGivenCatalog($composerJsonDecoded['autoload-dev'], $catalogToLoad));
            }
        }

        $namespacesToUse = array_map(function(string $namespace) {
            return trim($namespace, "\t\n\r\\");
        }, $namespacesToUse);

        if ((!$namespacesToUse || $namespacesToUse === [FileSystemAnnotationRegistrationService::FRAMEWORK_NAMESPACE]) && $catalogToLoad) {
            throw ConfigurationException::create("Ecotone cannot resolve namespaces in {$rootProjectDir}/$catalogToLoad. Please provide namespaces manually via configuration. If you do not know how to do it, read Modules section related to your framework at https://docs.ecotone.tech");
        }

        $paths = $this->getPathsToSearchIn($autoloadNamespaceParser, $rootProjectDir, $namespacesToUse);

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                $fileName = $file->getBasename(self::FILE_EXTENSION);

                if ($this->isDirectory($fileName, $file)) {
                    continue;
                }
                if ($this->isPHPFile($file)) {
                    continue;
                }

                $file = $file->openFile();
                while (!$file->eof()) {
                    $line = $file->current();
                    if ($line == false) {
                        break;
                    }

                    if (preg_match_all("#namespace[\s]*([^\n\s\(\)\[\]\{\}\$]*);#", $line, $results)) {
                        $namespace = isset($results[1][0]) ? trim($results[1][0]) : "";
                        $namespace = trim($namespace, "\t\n\r\\");

//                        Add all in resolved paths
                        if ($this->isInAvailableNamespaces($namespacesToUse, $namespace)) {
                            $classes[] = $namespace . '\\' . $fileName;
                            break;
                        }
                    }

                    $file->next();
                }
            }
        }

        $this->registeredClasses = array_unique($classes);
    }

    /**
     * @param AutoloadNamespaceParser $autoloadNamespaceParser
     * @param string $rootProjectDir
     * @param array $namespaces
     * @return array
     */
    private function getPathsToSearchIn(AutoloadNamespaceParser $autoloadNamespaceParser, string $rootProjectDir, array $namespaces): array
    {
        $paths = [];

        $autoloadPsr4 = require($rootProjectDir . '/vendor/composer/autoload_psr4.php');
        $autoloadPsr0 = require($rootProjectDir . '/vendor/composer/autoload_namespaces.php');
        $paths = array_merge($paths, $autoloadNamespaceParser->getFor($namespaces, $autoloadPsr4, true));
        $paths = array_merge($paths, $autoloadNamespaceParser->getFor($namespaces, $autoloadPsr0, false));

        return array_unique($paths);
    }

    /**
     * @param $fileName
     * @param $file
     * @return bool
     */
    private function isDirectory($fileName, SplFileInfo $file): bool
    {
        return $fileName == $file->getBasename();
    }

    /**
     * @param $file
     * @return bool
     */
    private function isPHPFile(SplFileInfo $file): bool
    {
        return $file->getFileInfo()->getExtension() == self::FILE_EXTENSION;
    }

    /**
     * @param array $namespaces
     * @param $namespace
     * @return bool
     */
    private function isInAvailableNamespaces(array $namespaces, $namespace): bool
    {
        foreach ($namespaces as $namespaceToUse) {
            if (strpos($namespace, $namespaceToUse) === 0) {
                $namespaceSuffix = str_replace($namespaceToUse, "", $namespace);

                if ($namespaceSuffix === "") {
                    return true;
                }

                return $namespaceSuffix[0] === "\\";
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function getAllClassesWithAnnotation(string $annotationClassName): array
    {
        if ($annotationClassName == "*") {
            return $this->registeredClasses;
        }

        $classesWithAnnotations = [];
        foreach ($this->registeredClasses as $class) {
            $classAnnotation = $this->getAnnotationForClass($class, $annotationClassName);

            if ($classAnnotation) {
                $classesWithAnnotations[] = $class;
            }
        }

        return $classesWithAnnotations;
    }

    /**
     * @inheritDoc
     */
    public function getAnnotationForClass(string $className, string $annotationClassNameToFind)
    {
        $annotationsForClass = $this->getAnnotationsForClass($className);

        foreach ($annotationsForClass as $annotationForClass) {
            if (get_class($annotationForClass) == $annotationClassNameToFind) {
                return $annotationForClass;
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function getAnnotationsForClass(string $className): iterable
    {
        return $this->getCachedAnnotationsForClass($className);
    }

    /**
     * @inheritDoc
     */
    private function getCachedAnnotationsForClass(string $className): array
    {
        if (isset($this->cachedClassAnnotations[$className])) {
            return $this->cachedClassAnnotations[$className];
        }


        $reflectionClass = new ReflectionClass($className);
        $classAnnotations = $this->annotationReader->getClassAnnotations($reflectionClass);

        $this->cachedClassAnnotations[$className] = $classAnnotations;
        return $classAnnotations;
    }

    /**
     * @param string $className
     * @param string $methodName
     * @return object[]
     * @throws ConfigurationException
     * @throws MessagingException
     */
    private function getCachedMethodAnnotations(string $className, string $methodName): array
    {
        if (isset($this->cachedMethodAnnotations[$className . $methodName])) {
            return $this->cachedMethodAnnotations[$className . $methodName];
        }

        try {
            $reflectionMethod = TypeResolver::getMethodOwnerClass(new ReflectionClass($className), $methodName)->getMethod($methodName);

            $annotations = $this->annotationReader->getMethodAnnotations($reflectionMethod);
        } catch (ReflectionException $e) {
            throw ConfigurationException::create("Class {$className} with method {$methodName} does not exists or got annotation configured wrong: " . $e->getMessage());
        }

        $this->cachedMethodAnnotations[$className . $methodName] = $annotations;

        return $annotations;
    }

    /**
     * @inheritDoc
     */
    public function findRegistrationsFor(string $classAnnotationName, string $methodAnnotationClassName): array
    {
        $registrations = [];
        foreach ($this->getAllClassesWithAnnotation($classAnnotationName) as $className) {
            foreach (get_class_methods($className) as $method) {
                if ($this->isMethodBannedFromCurrentEnvironment($className, $method)) {
                    continue;
                }

                $methodAnnotations = $this->getCachedMethodAnnotations($className, $method);
                foreach ($methodAnnotations as $methodAnnotation) {
                    if (get_class($methodAnnotation) === $methodAnnotationClassName || $methodAnnotation instanceof $methodAnnotationClassName) {
                        $annotationRegistration = AnnotationRegistration::create(
                            $this->getAnnotationForClass($className, $classAnnotationName),
                            $methodAnnotation,
                            $className,
                            $method
                        );

                        $registrations[] = $annotationRegistration;
                    }
                }
            }
        }

        usort($registrations, function (AnnotationRegistration $annotationRegistration, AnnotationRegistration $annotationRegistrationToCheck) {
            if ($annotationRegistration->getClassName() == $annotationRegistrationToCheck->getClassName()) {
                return 0;
            }

            return $annotationRegistration->getClassName() > $annotationRegistrationToCheck->getClassName();
        });

        return $registrations;
    }

    /**
     * @param string $className
     * @param string $methodName
     * @return bool
     */
    private function isMethodBannedFromCurrentEnvironment(string $className, string $methodName)
    {
        return isset($this->bannedEnvironmentClassMethods[$className][$methodName]);
    }

    /**
     * @inheritDoc
     */
    public function getAnnotationsForMethod(string $className, string $methodName): iterable
    {
        return $this->getCachedMethodAnnotations($className, $methodName);
    }

    /**
     * @inheritDoc
     */
    public function getAnnotationsForProperty(string $className, string $propertyName): iterable
    {
        $reflectionProperty = new ReflectionProperty($className, $propertyName);

        return $this->annotationReader->getPropertyAnnotations($reflectionProperty);
    }
}