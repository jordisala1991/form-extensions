<?php

declare(strict_types=1);

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\Form\Type;

use Sonata\Form\Date\MomentFormatConverter;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatorInterface as LegacyTranslatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class BasePickerType (to factorize DatePickerType and DateTimePickerType code.
 *
 * @author Hugo Briand <briand@ekino.com>
 */
abstract class BasePickerType extends AbstractType
{
    /**
     * @var TranslatorInterface|LegacyTranslatorInterface|null
     */
    protected $translator;

    /**
     * @var string
     */
    protected $locale;

    /**
     * @var MomentFormatConverter
     */
    private $formatConverter;

    public function __construct(MomentFormatConverter $formatConverter, $translator, ?RequestStack $requestStack = null)
    {
        if (!$translator instanceof LegacyTranslatorInterface && !$translator instanceof TranslatorInterface) {
            throw new \InvalidArgumentException(sprintf(
                'Argument 2 should be an instance of %s or %s',
                LegacyTranslatorInterface::class,
                TranslatorInterface::class
            ));
        }

        if (null === $requestStack) {
            if ($translator instanceof TranslatorInterface) {
                throw new \InvalidArgumentException(sprintf(
                    'Argument 3 should be an instance of %s',
                    RequestStack::class
                ));
            }

            @trigger_error(sprintf(
                'Not passing the request stack as argument 3 to %s() is deprecated since sonata-project/form-extensions 1.2 and will be mandatory in 2.0.',
                __METHOD__
            ), E_USER_DEPRECATED);
        }

        $this->formatConverter = $formatConverter;
        $this->translator = $translator;

        if ($translator instanceof LegacyTranslatorInterface) {
            $this->locale = $this->translator->getLocale();
        } else {
            $this->locale = $this->getLocale($requestStack);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setNormalizer('format', function (Options $options, $format) {
            if (isset($options['date_format']) && \is_string($options['date_format'])) {
                return $options['date_format'];
            }

            if (\is_int($format)) {
                $timeFormat = \IntlDateFormatter::NONE;
                if ($options['dp_pick_time']) {
                    $timeFormat = $options['dp_use_seconds'] ?
                        DateTimeType::DEFAULT_TIME_FORMAT :
                        \IntlDateFormatter::SHORT;
                }
                $intlDateFormatter = new \IntlDateFormatter(
                    $this->locale,
                    $format,
                    $timeFormat,
                    null,
                    \IntlDateFormatter::GREGORIAN
                );

                return $intlDateFormatter->getPattern();
            }

            return $format;
        });
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $format = $options['format'];

        // use seconds if it's allowed in format
        $options['dp_use_seconds'] = false !== strpos($format, 's');

        if ($options['dp_min_date'] instanceof \DateTime) {
            $options['dp_min_date'] = $this->formatObject($options['dp_min_date'], $format);
        }
        if ($options['dp_max_date'] instanceof \DateTime) {
            $options['dp_max_date'] = $this->formatObject($options['dp_max_date'], $format);
        }

        $view->vars['moment_format'] = $this->formatConverter->convert($format);

        $view->vars['type'] = 'text';

        $dpOptions = [];
        foreach ($options as $key => $value) {
            if (false !== strpos($key, 'dp_')) {
                // We remove 'dp_' and camelize the options names
                $dpKey = substr($key, 3);
                $dpKey = preg_replace_callback('/_([a-z])/', static function ($c) {
                    return strtoupper($c[1]);
                }, $dpKey);

                $dpOptions[$dpKey] = $value;
            }
        }

        $view->vars['datepicker_use_button'] = empty($options['datepicker_use_button']) ? false : true;
        $view->vars['dp_options'] = $dpOptions;
    }

    /**
     * Gets base default options for the date pickers.
     */
    protected function getCommonDefaults(): array
    {
        return [
            'widget' => 'single_text',
            'datepicker_use_button' => true,
            'dp_pick_time' => true,
            'dp_pick_date' => true,
            'dp_use_current' => true,
            'dp_min_date' => '1/1/1900',
            'dp_max_date' => null,
            'dp_show_today' => true,
            'dp_language' => $this->locale,
            'dp_default_date' => '',
            'dp_disabled_dates' => [],
            'dp_enabled_dates' => [],
            'dp_icons' => [
                'time' => 'fa fa-clock-o',
                'date' => 'fa fa-calendar',
                'up' => 'fa fa-chevron-up',
                'down' => 'fa fa-chevron-down',
            ],
            'dp_use_strict' => false,
            'dp_side_by_side' => false,
            'dp_days_of_week_disabled' => [],
            'dp_collapse' => true,
            'dp_calendar_weeks' => false,
            'dp_view_mode' => 'days',
            'dp_min_view_mode' => 'days',
        ];
    }

    private function getLocale(RequestStack $requestStack): string
    {
        if (!$request = $requestStack->getCurrentRequest()) {
            throw new \LogicException('A Request must be available.');
        }

        return $request->getLocale();
    }

    private function formatObject(\DateTime $dateTime, $format): string
    {
        $formatter = new \IntlDateFormatter($this->locale, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE);
        $formatter->setPattern($format);

        return $formatter->format($dateTime);
    }
}
