<?php declare(strict_types=1);

namespace DheeStyle\Sniffs\ControlStructures;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;


/**
 * Vyžaduje nový řádek před else / elseif / catch / finally.
 *
 * Správně:
 *   }
 *   else {
 *
 * Špatně:
 *   } else {
 */
class NewlineBeforeKeywordSniff implements Sniff
{

	public function register(): array
	{
		return [T_ELSE, T_ELSEIF, T_CATCH, T_FINALLY];
	}



	public function process(File $phpcsFile, int $stackPtr): void
	{
		$tokens = $phpcsFile->getTokens();

		$closer = $phpcsFile->findPrevious(Tokens::EMPTY_TOKENS, ($stackPtr - 1), null, true);
		if ($closer === false || $tokens[$closer]['code'] !== T_CLOSE_CURLY_BRACKET) {
			return;
		}

		if ($tokens[$closer]['line'] !== $tokens[$stackPtr]['line']) {
			return;
		}

		$keyword    = $tokens[$stackPtr]['content'];
		$hasComment = ($phpcsFile->findNext(Tokens::COMMENT_TOKENS, ($closer + 1), $stackPtr) !== false);

		if ($hasComment) {
			$phpcsFile->addError(
				'Expected newline before "%s"',
				$stackPtr,
				'NewlineBeforeKeyword',
				[$keyword]
			);
			return;
		}

		$fix = $phpcsFile->addFixableError(
			'Expected newline before "%s"',
			$stackPtr,
			'NewlineBeforeKeyword',
			[$keyword]
		);

		if ($fix !== true) {
			return;
		}

		$indent = $this->getLineIndent($phpcsFile, $closer);

		$phpcsFile->fixer->beginChangeset();
		for ($i = ($closer + 1); $i < $stackPtr; $i++) {
			$phpcsFile->fixer->replaceToken($i, '');
		}
		$phpcsFile->fixer->addContentBefore($stackPtr, $phpcsFile->eolChar . $indent);
		$phpcsFile->fixer->endChangeset();
	}



	private function getLineIndent(File $phpcsFile, int $tokenPtr): string
	{
		$tokens  = $phpcsFile->getTokens();
		$lineNum = $tokens[$tokenPtr]['line'];

		$ptr = $tokenPtr - 1;
		while ($ptr >= 0 && $tokens[$ptr]['line'] === $lineNum) {
			$ptr--;
		}
		$ptr++;

		if ($tokens[$ptr]['code'] !== T_WHITESPACE) {
			return '';
		}

		$content = $tokens[$ptr]['content'];
		$lastNl  = strrpos($content, $phpcsFile->eolChar);
		if ($lastNl !== false) {
			return substr($content, $lastNl + strlen($phpcsFile->eolChar));
		}

		return $content;
	}

}
