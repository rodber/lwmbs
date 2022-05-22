<?php
/**
 * Copyright (c) 2022 Yun Dou <dixyes@gmail.com>
 *
 * lwmbs is licensed under Mulan PSL v2. You can use this
 * software according to the terms and conditions of the
 * Mulan PSL v2. You may obtain a copy of Mulan PSL v2 at:
 *
 * http://license.coscl.org.cn/MulanPSL2
 *
 * THIS SOFTWARE IS PROVIDED ON AN "AS IS" BASIS,
 * WITHOUT WARRANTIES OF ANY KIND, EITHER EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO NON-INFRINGEMENT,
 * MERCHANTABILITY OR FIT FOR A PARTICULAR PURPOSE.
 *
 * See the Mulan PSL v2 for more details.
 */

declare(strict_types=1);

class Config extends CommonConfig
{
    // TODO: workspace
    //public string $workspace = '.';
    public string $setX = 'set -x';
    public string $configureEnv = '';
    // TODO: comment
    //public string $noteSection = "Je pense, donc je suis\0";
    public string $arch = 'x86_64';
    public string $cmakeToolchainFile;
    public const NEEDED_COMMANDS = ['gcc', 'make', 'bison', 'flex', 'pkg-config', 'git', 'autoconf', 'automake', 'tar', 'unzip', 'xz', 'gzip', 'bzip2', 'cmake'];

    public function __construct()
    {
        $lackingCommands = Util::lackingCommands(static::NEEDED_COMMANDS);
        if ($lackingCommands) {
            throw new Exception("missing commands: " . implode(', ', $lackingCommands));
        }
        @mkdir('lib/pkgconfig', recursive: true);
        $this->configureEnv = 'PKG_CONFIG_PATH=' . realpath('lib/pkgconfig');
        $this->concurrency = Util::getCpuCount();
        $this->archCFlags = Util::getArchCFlags($this->arch);
        $this->cmakeToolchainFile = Util::makeCmakeToolchainFile(
            os: 'Darwin',
            targetArch: $this->arch,
            cflags:  Util::getArchCFlags($this->arch),
        );
    }

    public function makeAutoconfArgs(string $name, array $libSpecs): string
    {
        $ret = '';
        foreach ($libSpecs as $libName => $arr) {
            $lib = $this->getLib($libName);

            $arr = $arr ?? [];

            $disableArgs = $arr[0] ?? null;
            $prefix = $arr[1] ?? null;
            if ($lib) {
                Log::i("{$name} \033[32;1mwith\033[0;1m {$libName} support");
                $ret .= $lib->makeAutoconfEnv($prefix) . ' ';
            } else {
                Log::i("{$name} \033[31;1mwithout\033[0;1m {$libName} support");
                $ret .= ($disableArgs ?? "--with-$libName=no") . ' ';
            }
        }
        return rtrim($ret);
    }

    public function getAllStaticLibFiles(): string
    {
        $libs = [];

        // reorder libs
        foreach ($this->libs as $lib) {
            foreach ($lib->getDependencies() as $dep) {
                array_push($libs, $dep);
            }
            array_push($libs, $lib);
        }
    
        return implode(' ', array_map(fn ($x) => $x->getStaticLibFiles(), $libs));
    }
}
