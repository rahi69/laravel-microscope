<?php

namespace Imanghafoori\LaravelMicroscope\Checks;

use Imanghafoori\LaravelMicroscope\Analyzers\ComposerJson;
use Imanghafoori\LaravelMicroscope\Analyzers\Fixer;
use Imanghafoori\LaravelMicroscope\CheckClassReferencesAreValid;
use Imanghafoori\LaravelMicroscope\ErrorReporters\ErrorPrinter;
use Imanghafoori\LaravelMicroscope\Psr4\NamespaceCalculator;
use Imanghafoori\TokenAnalyzer\ParseUseStatement;

class CheckImport
{
    private static function checkImports($currentNamespace, $className, $absPath, $tokens)
    {
        $namespacedClassName = self::fullNamespace($currentNamespace, $className);

        $imports = ParseUseStatement::parseUseStatements($tokens, $namespacedClassName)[1];

        return self::checkImportedClassesExist($imports, $absPath);
    }

    protected static function fullNamespace($currentNamespace, $class)
    {
        return $currentNamespace ? $currentNamespace.'\\'.$class : $class;
    }

    private static function checkImportedClassesExist($imports, $absFilePath)
    {
        $printer = app(ErrorPrinter::class);
        $fixed = false;

        foreach ($imports as $as => $import) {
            [$classImport, $line] = $import;

            if (! self::isAbsent($classImport)) {
                continue;
            }

            // for half imported namespaces
            if (\is_dir(base_path(NamespaceCalculator::getRelativePathFromNamespace($classImport, ComposerJson::readAutoload())))) {
                continue;
            }

            $isCorrected = self::tryToFix($classImport, $absFilePath, $line, $as, $printer);

            if (! $isCorrected) {
                $printer->wrongImport($absFilePath, $classImport, $line);
            } else {
                $fixed = true;
            }
        }

        return $fixed;
    }

    private static function tryToFix($classImport, $absFilePath, $line, $as, $printer)
    {
        $isInUserSpace = CheckClassReferencesAreValid::isInUserSpace($classImport);
        if (! $isInUserSpace) {
            return false;
        }

        [$isCorrected, $corrects] = Fixer::fixImport($absFilePath, $classImport, $line, self::isAliased($classImport, $as));

        if ($isCorrected) {
            $printer->printFixation($absFilePath, $classImport, $line, $corrects);
        }

        return $isCorrected;
    }

    private static function isAliased($class, $as)
    {
        return class_basename($class) !== $as;
    }
}
