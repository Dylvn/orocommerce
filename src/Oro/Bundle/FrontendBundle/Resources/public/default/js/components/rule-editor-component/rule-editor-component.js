define([
    'jquery',
    'underscore',
    'oroui/js/app/components/base/component'
], function($, _, BaseComponent) {
    'use strict';

    var RuleEditorComponent;

    RuleEditorComponent = BaseComponent.extend({
        options: null,
        $element: null,

        /**
         *
         * @param options
         */
        initialize: function(options) {
            this.options = _.defaults(options || {}, this.options);
            this.$element = this.options._sourceElement.find('input[type="text"]').eq(0);

            var self = this;

            this.$element.on('keyup paste', function() {
                var value = self.$element.val().trim().replace(/\s+/g, ' ');

                self.$element.toggleClass('error', !self.validate(value, self.options));
            });
            this.$element.on('keyup change paste', function() {
                self.autocomplete(self.$element, self.options);
            });
        },

        validate: function(value, options) {
            if (value === '') {
                return true;
            }

            var self = this;

            var opsRegEx = this.getRegexp(options.operations);

            var words = _.isRegExp(opsRegEx) ? this.splitString(value.replace(opsRegEx, '$1'), ' ').arr : [],
                groups = this.getGroups(words);

            var logicWordIsLast = _.last(groups.logic) === _.last(words),
                logicWordsAreValid = (function(arr, ref) {
                    return _.every(arr, function(item) {
                        return _.contains(ref, item);
                    });
                })(groups.logic, options.grouping),
                logicIsValid = !logicWordIsLast && logicWordsAreValid;

            var dataWordsAreValid = (function(arr, refs) {
                var isValid = logicIsValid;

                _.each(arr, function(item) {
                    if (isValid) {
                        var expressionMatch = item.match(opsRegEx);
                        var matchSplit = expressionMatch ? self.splitString(item, expressionMatch[0]).arr : [];

                        isValid = !_.isNull(expressionMatch) && matchSplit[1] !== '';

                        if (isValid) {
                            var path = self.splitString(matchSplit[0] || item, '.').arr;
                            var currentRef = refs;

                            _.each(path, function(pathItem) {
                                if (isValid) {
                                    isValid = _.contains(self.getPathsArray(currentRef), pathItem);

                                    if (isValid && _.last(path) !== pathItem) {
                                        currentRef = self.getRef(refs, pathItem, currentRef);
                                    }
                                }
                            });
                        }
                    }
                });

                return isValid;
            })(groups.datas, options.data);

            return logicIsValid && dataWordsAreValid;
        },

        autocomplete: function($element, options) {
            var self = this,
                refs = options,
                value = $element.val(),
                caretPosition = $element[0].selectionStart,
                separatorsPositions = (function(string) {
                    var arr = [0];

                    _.each(string, function(char, i) {
                        if (self.isSpace(char)) {
                            arr.push(i + 1);
                        }
                    });

                    arr.push(string.length + 1);

                    return arr;
                })(value),
                nearestSeparator = (function(arr, position) {
                    var index = 0;

                    if (!arr.length) {
                        return index;
                    }


                    while (arr[index] < position) {
                        index++;
                    }

                    return {
                        position: arr[index] === position ? null : arr[index - 1],
                        index: index
                    };

                })(separatorsPositions, caretPosition),
                wordUnderCaret = this.getStringPart(value, nearestSeparator.position, separatorsPositions[nearestSeparator.index] - 1),
                suggested = (function(word, ref) {
                    return _.filter(self.getPathsArray(ref.data), function(item) {
                        return item.indexOf(word) === 0;
                    });
                })(wordUnderCaret, refs);


            console.log(wordUnderCaret, suggested);
        },

        getPathsArray: function(src, baseName, baseArr) {
            var self = this,
                arr = [baseName];

            _.each(src, function(item, name) {
                var subName = (baseName ? (baseName + '.') : '') + item;

                if (_.isArray(item)) {
                    arr = _.union(arr, self.getPathsArray(item, name, baseArr || src));
                } else if (_.isString(item) && _.isArray(baseArr[item])) {
                    arr = _.union(arr, self.getPathsArray(baseArr[item], subName, baseArr || src));
                } else {
                    arr.push(subName);
                }
            });

            return _.compact(arr);
        },

        getStringPart: function(string, startPos, endPos) {
            return _.isNull(startPos) ? null : string.substr(startPos, endPos - startPos);
        },

        isSpace: function(char) {
            return /^\s$/.test(char);
        },

        getRegexp: function(opsArr) {
            var escapedOps = (function(ops) {
                var result = [];

                if (ops && ops.length) {
                    _.each(ops, function(item) {
                        result.push('\\' + item.split('').join('\\'));
                    });
                }

                return result;
            })(opsArr);

            return escapedOps && escapedOps.length ? new RegExp('\\s*((' + escapedOps.join(')|(') + '))\\s*', 'g') : null;
        },

        getStringParts: function(string, sub) {
            var stringLength = string.length,
                subLength = sub.length,
                indexOf = string.indexOf(sub);

            return indexOf === -1 || !subLength ? null : {
                before: indexOf !== 0 ? string.substr(0, subLength) : '',
                after: string.substr(indexOf + subLength, stringLength - subLength - indexOf),
                sub: sub
            };
        },

        splitString: function(string, splitter) {
            var arr = string.split(splitter);

            return {
                arr: arr,
                hasParts: arr.length > 1
            };
        },

        getGroups: function(words) {
            return {
                datas: separateGroups(words, true),
                logic: separateGroups(words)
            };

            function separateGroups(groups, isOdd) {
                return _.filter(groups, function(item, i) {
                    var modulo = i % 2;
                    return isOdd ? !modulo : modulo;
                });
            }
        },

        getRef: function(refs, pathItem, currentRef) {
            return _.constant(refs, pathItem) ? refs[pathItem] : currentRef[pathItem];
        }
    });

    return RuleEditorComponent;
});
