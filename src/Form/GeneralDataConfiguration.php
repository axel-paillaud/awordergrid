<?php
/**
 * @author    Axelweb <contact@axelweb.fr>
 * @copyright 2026 Axelweb
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of Axelweb
 */
declare(strict_types=1);

namespace Axelweb\AwOrderGrid\Form;

use PrestaShop\PrestaShop\Core\Configuration\DataConfigurationInterface;
use PrestaShop\PrestaShop\Core\ConfigurationInterface;

/**
 * Configuration is used to save data to configuration table and retrieve from it.
 */
final class GeneralDataConfiguration implements DataConfigurationInterface
{
    public const AWORDERGRID_SAMPLE_CONFIG = 'AWORDERGRID_SAMPLE_CONFIG';

    /**
     * @var ConfigurationInterface
     */
    private $configuration;

    public function __construct(ConfigurationInterface $configuration)
    {
        $this->configuration = $configuration;
    }

    public function getConfiguration(): array
    {
        return [
            'sample_config' => (string) $this->configuration->get(static::AWORDERGRID_SAMPLE_CONFIG),
        ];
    }

    public function updateConfiguration(array $configuration): array
    {
        $errors = [];

        // Quick normalisation
        $sampleConfig = isset($configuration['sample_config']) ? trim((string) $configuration['sample_config']) : '';

        if (!$this->validateConfiguration(['sample_config' => $sampleConfig])) {
            $errors[] = 'Invalid configuration payload.';

            return $errors;
        }

        // Validation (exemple)
        if ($sampleConfig !== '' && \strlen($sampleConfig) > 255) {
            $errors[] = 'Sample configuration is too long.';
        }

        if (!empty($errors)) {
            return $errors;
        }

        // Persist
        $this->configuration->set(static::AWORDERGRID_SAMPLE_CONFIG, $sampleConfig);

        // empty = ok
        return $errors;
    }

    /**
     * Ensure the parameters passed are valid.
     *
     * @return bool Returns true if no exception are thrown
     */
    public function validateConfiguration(array $configuration): bool
    {
        return isset($configuration['sample_config']);
    }
}
