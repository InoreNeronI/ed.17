<?php

namespace App\Model;

/**
 * Class PagesModel.
 */
final class PagesModel extends CredentialsModel
{
    /** @var array */
    private static $pageTexts = ['id' => '', 'texts' => []];

    /**
     * @param array  $args
     * @param string $wildcardA
     * @param string $wildcardB
     *
     * @return array
     *
     * @throws \Exception
     */
    private function loadData(array $args, $wildcardA, $wildcardB)
    {
        /** @var \Doctrine\DBAL\Query\QueryBuilder $queryBuilder */
        $queryBuilder = $this->getQueryBuilder()
            ->select('t.edg051_codprueba as id', 't.edg051_cod_texto as text_code', 'TRIM(t.edg051_texto_' . $args['flengua'] . ') as text_string')
            ->from($args['ftestuak'], 't')
            ->where('t.edg051_periodo = :periodo')
            ->andWhere('t.edg051_cod_texto LIKE :text_code')
            ->orderBy('t.edg051_cod_texto')
            ->setParameters(['periodo' => $args['fcurso'], 'text_code' => 'p' . $wildcardA . $wildcardB . '%']);

        /** @var array $texts */
        $texts = $queryBuilder->execute()->fetchAll();
        if (!empty($texts)) {
            static::$pageTexts['id'] = $texts[0]['id'];
            static::$pageTexts['texts'][$wildcardB] = [];
            foreach ($texts as $text) {
                static::$pageTexts['texts'][$wildcardB][$text['text_code']] = $text['text_string'];
            }

            return static::$pageTexts;
        } else {
            throw new \Exception(sprintf('No results found for query: %s, with the following parameter values: [%s]', $queryBuilder->getSQL(), implode(', ', $queryBuilder->getParameters())));
        }
    }

    /**
     * @param array  $args
     * @param string $page
     *
     * @return array
     *
     * @throws \Exception
     */
    public function loadPageData(array $args, $page)
    {
        $sideA = $this->loadData($args, $page, 'a');
        $configPath = DATA_DIR . '/' . $sideA['id'];
        $config = parseConfig($configPath, 'config');

        if (empty($config)) {
            throw new \Exception(sprintf('The configuration file is missing in the target: %s', $configPath));
        }

        return array_merge($args, $sideA, $this->loadData($args, $page, 'b'), [
            'id' => $sideA['id'],
            'page' => $page,
            'totalPages' => $config['pages'],
            'config' => ['a' => parseConfig($configPath, 'p' . $page . 'a'), 'b' => parseConfig($configPath, 'p' . $page . 'b')],
            'lang' => $args['lang'],
        ]);
    }
}
