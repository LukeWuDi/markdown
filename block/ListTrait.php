<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown\block;


trait ListTrait
{
	/**
	 * @var bool enable support `start` attribute of ordered lists. This means that lists
	 * will start with the number you actually type in markdown and not the HTML generated one.
	 * Defaults to `false` which means that numeration of all ordered lists(<ol>) starts with 1.
	 */
	public $keepListStartNumber = false;

	protected function identifyOl($line)
	{
		return (is_numeric($line[0]) || $line[0] === ' ') && preg_match('/^ {0,3}\d+\.[ \t]/', $line);
	}

	protected function identifyUl($line)
	{
		return ($line[0] === '-' || $line[0] === '+' || $line[0] === '*') && (isset($line[1]) && ($line[1] === ' ' || $line[1] === "\t")) ||
		       ($line[0] === ' ' && preg_match('/^ {0,3}[\-\+\*][ \t]/', $line));


	}

	/**
	 * Consume lines for an ordered list
	 */
	protected function consumeOl($lines, $current)
	{
		// consume until newline

		$block = [
			'list',
			'list' => 'ol',
			'attr' => [],
			'items' => [],
		];
		return $this->consumeList($lines, $current, $block, 'ol');
	}

	/**
	 * Consume lines for an unordered list
	 */
	protected function consumeUl($lines, $current)
	{
		// consume until newline

		$block = [
			'list',
			'list' => 'ul',
			'items' => [],
		];
		return $this->consumeList($lines, $current, $block, 'ul');
	}

	private function consumeList($lines, $current, $block, $type)
	{
		$item = 0;
		$indent = '';
		$len = 0;
		// track the indentation of list markers, if indented more than previous element
		// a list marker is considered to be long to a lower level
		$leadSpace = 3;
		for ($i = $current, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];
			// match list marker on the beginning of the line
			if (preg_match($type == 'ol' ? '/^( {0,'.$leadSpace.'})(\d+)\.[ \t]+/' : '/^( {0,'.$leadSpace.'})[\-\+\*][ \t]+/', $line, $matches)) {
				if (($len = substr_count($matches[0], "\t")) > 0) {
					$indent = str_repeat("\t", $len);
					$line = substr($line, strlen($matches[0]));
				} else {
					$len = strlen($matches[0]);
					$indent = str_repeat(' ', $len);
					$line = substr($line, $len);
				}
				if ($i === $current) {
					$leadSpace = strlen($matches[1]) + 1;
				}

				if ($type == 'ol' && $this->keepListStartNumber) {
					// attr `start` for ol
					if (!isset($block['attr']['start']) && isset($matches[2])) {
						$block['attr']['start'] = $matches[2];
					}
				}

				$block['items'][++$item][] = $line;
			} elseif (ltrim($line) === '') {
				// next line after empty one is also a list or indented -> lazy list
				if (isset($lines[$i + 1][0]) && (
					$this->{'identify' . $type}($lines[$i + 1], $lines, $i + 1) ||
					(strncmp($lines[$i + 1], $indent, $len) === 0 || !empty($lines[$i + 1]) && $lines[$i + 1][0] == "\t"))) {
					$block['items'][$item][] = $line;
					$block['lazyItems'][$item] = true;
				} else {
					break;
				}
			} else {
				if ($line[0] === "\t") {
					$line = substr($line, 1);
				} elseif (strncmp($line, $indent, $len) === 0) {
					$line = substr($line, $len);
				}
				$block['items'][$item][] = $line;
			}
		}

		// make last item lazy if item before was lazy
		if (isset($block['lazyItems'][$item - 1])) {
			$block['lazyItems'][$item] = true;
		}

		foreach($block['items'] as $itemId => $itemLines) {
			$content = [];
			if (!isset($block['lazyItems'][$itemId])) {
				$firstPar = [];
				while (!empty($itemLines) && rtrim($itemLines[0]) !== '' && $this->getLineType($itemLines, 0) === 'paragraph') { // TODO
					$firstPar[] = array_shift($itemLines);
				}
				$content = $this->parseInline(implode("\n", $firstPar));
			}
			if (!empty($itemLines)) {
				$content = array_merge($content, $this->parseBlocks($itemLines));
			}
			$block['items'][$itemId] = $content;
		}

		return [$block, $i];
	}

	/**
	 * Renders a list
	 */
	protected function renderList($block)
	{
		$type = $block['list'];

		if (!empty($block['attr'])) {
			$output = "<$type " . $this->generateHtmlAttributes($block['attr']) . ">\n";
		} else {
			$output = "<$type>\n";
		}

		foreach ($block['items'] as $item => $itemLines) {
			$output .= '<li>' . $this->renderAbsy($itemLines). "</li>\n";
		}
		return $output . "</$type>\n";
	}


	/**
	 * Return html attributes string from [attrName => attrValue] list
	 * @param array $attributes the attribute name-value pairs.
	 * @return string
	 */
	private function generateHtmlAttributes($attributes)
	{
		foreach ($attributes as $name => $value) {
			$attributes[$name] = "$name=\"$value\"";
		}
		return implode(' ', $attributes);
	}

} 