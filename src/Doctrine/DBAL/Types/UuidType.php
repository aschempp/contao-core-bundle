<?php

namespace Contao\CoreBundle\Doctrine\DBAL\Types;

use Doctrine\DBAL\Types\BinaryType;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Type that maps a UUID string to a binary SQL type.
 *
 * @author Andreas Schempp <https://github.com/aschempp>
 * @link https://blog.vandenbrand.org/2015/06/25/creating-a-custom-doctrine-dbal-type-the-right-way/
 */
class UuidType extends BinaryType
{
    const UUID = 'uuid';

    /**
     * @inheritDoc
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        if (!$fieldDeclaration['length']) {
            $fieldDeclaration['length'] = 16;
            $fieldDeclaration['fixed']  = true;
        }

        return parent::getSQLDeclaration($fieldDeclaration, $platform);
    }

    /**
     * Converts the binary UUID to string representation.
     *
     * @param mixed            $value
     * @param AbstractPlatform $platform
     * @return string
     */
    public function convertToPhpValue($value, AbstractPlatform $platform)
    {
        if (16 !== strlen($value) || ($value & hex2bin('000000000000F000C000000000000000')) !== hex2bin('00000000000010008000000000000000')) {
            return null;
        }

        return implode('-', unpack('H8time_low/H4time_mid/H4time_high/H4clock_seq/H12node', $value));
    }

    /**
     * Converts the string UUID to binary representation.
     *
     * @param mixed            $value
     * @param AbstractPlatform $platform
     * @return string
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if (36 !== strlen($value) || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-1[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $value)) {
            return null;
        }

        return hex2bin(str_replace('-', '', $value));
    }

    /**
     * {@inheritdoc}
     */
    public function requiresSQLCommentHint(AbstractPlatform $platform)
    {
        return true;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return self::UUID;
    }
}
