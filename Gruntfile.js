/*jslint node: true */
module.exports = function (grunt) {
    'use strict';
    grunt.initConfig(
        {
            phpcs: {
                options: {
                    standard: 'PSR2',
                    bin: 'vendor/bin/phpcs'
                },
                php: {
                    src: ['*.php', 'classes/*.php', 'controllers/*.php']
                }
            },
            jslint: {
                Gruntfile: {
                    src: ['Gruntfile.js']
                }
            },
            jsonlint: {
                manifests: {
                    src: '*.json',
                    options: {
                        format: true
                    }
                }
            },
            fixpack: {
                package: {
                    src: 'package.json'
                }
            }
        }
    );

    grunt.loadNpmTasks('grunt-jslint');
    grunt.loadNpmTasks('grunt-phpcs');
    grunt.loadNpmTasks('grunt-jsonlint');
    grunt.loadNpmTasks('grunt-fixpack');

    grunt.registerTask('lint', ['jslint', 'fixpack', 'jsonlint', 'phpcs']);
};
