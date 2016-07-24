/*jslint node: true */
module.exports = function (grunt) {
    'use strict';
    grunt.initConfig(
        {
            phpcs: {
                options: {
                    standard: 'PSR2'
                },
                php: {
                    src: ['*.php', 'classes/*.php', 'controllers/*.php']
                }
            },
            jslint: {
                Gruntfile: {
                    src: ['Gruntfile.js']
                }
            }
        }
    );

    grunt.loadNpmTasks('grunt-jslint');
    grunt.loadNpmTasks('grunt-phpcs');

    grunt.registerTask('lint', ['phpcs', 'jslint']);
};
