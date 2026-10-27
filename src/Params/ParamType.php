<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Params;

/**
 * Where an input param is read from. Values match the InputParam::TYPE_* constants.
 */
enum ParamType: string
{
    case Post = InputParam::TYPE_POST;
    case Get = InputParam::TYPE_GET;
    case Put = InputParam::TYPE_PUT;
    case File = InputParam::TYPE_FILE;
    case Cookie = InputParam::TYPE_COOKIE;
    case PostRaw = InputParam::TYPE_POST_RAW;
    case PostJson = InputParam::TYPE_POST_JSON;
}
