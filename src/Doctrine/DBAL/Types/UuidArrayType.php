<?php

namespace Contao\CoreBundle\Doctrine\DBAL\Types;

use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Type that maps an array of UUIDs to a BLOB SQL type.
 *
 * @author Andreas Schempp <https://github.com/aschempp>
 * @link https://blog.vandenbrand.org/2015/06/25/creating-a-custom-doctrine-dbal-type-the-right-way/
 */
class UuidArrayType extends BlobType
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'uuid_array';
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

        $value = unserialize($value);

        if (!is_array($value)) {
            return null;
        }

        foreach ($value as $k => $v) {
            $value[$k] = implode('-', unpack('H8time_low/H4time_mid/H4time_high/H4clock_seq/H12node', $v));
        }

        return $value;
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
        if (null === $value || !is_array($value)) {
            return null;
        }

        foreach ($value as $k => $v) {
            $value[$k] = hex2bin(str_replace('-', '', $v));
        }

        return serialize($value);
    }

    /**
     * {@inheritdoc}
     */
    public function requiresSQLCommentHint(AbstractPlatform $platform)
    {
        return true;
    }
}
