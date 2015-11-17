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
    /**
     * @return string
     */
    public function getName()
    {
        return 'uuid';
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
        if (null === $value) {
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
        if (null === $value) {
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
}
