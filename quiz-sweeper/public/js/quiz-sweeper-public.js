(function( $ ) {
    'use strict';

    $(function() {
        if (typeof quiz_sweeper_student_ajax === 'undefined') {
            return;
        }

        var ajaxUrl = quiz_sweeper_student_ajax.ajax_url;
        var nonce = quiz_sweeper_student_ajax.nonce;
        var gameId = quiz_sweeper_student_ajax.game_id;
        var myGroupId = quiz_sweeper_student_ajax.group_id;

        var gameStateInterval;
        var boardEl = $('#quiz-sweeper-board');
        var scoresEl = $('#quiz-sweeper-scores');

        // Delegated event handler for cell clicks
        boardEl.on('click', '.cell:not(.revealed)', function() {
            var clickedCell = $(this);
            var row = clickedCell.data('row');
            var col = clickedCell.data('col');
            handleCellClick(row, col);
        });

        function getGameState() {
            $.get(ajaxUrl, { action: 'get_game_state', nonce: nonce, game_id: gameId }, function(response) {
                if (response.success) {
                    renderBoard(response.data.grid);
                    renderScores(response.data.scores);

                    var allRevealed = response.data.grid.every(function(cell) { return cell.is_revealed == 1; });
                    if (allRevealed && response.data.grid.length > 0) {
                        clearInterval(gameStateInterval);
                        showGameOver(response.data.scores);
                    }
                }
            });
        }

        function renderBoard(grid) {
            boardEl.empty();
            grid.forEach(function(cellData) {
                var cellEl = $('<div class="cell"></div>');
                cellEl.attr('data-row', cellData.row);
                cellEl.attr('data-col', cellData.col);

                if (cellData.is_revealed) {
                    cellEl.addClass('revealed');
                    var icon = '';
                    switch(cellData.type) {
                        case 'bomb': icon = '💣'; break;
                        case 'knife': icon = '🔪'; break;
                        case 'question':
                            if (cellData.was_correct == 1) {
                                icon = '✅';
                                cellEl.addClass('correct');
                            } else {
                                icon = '❌';
                                cellEl.addClass('incorrect');
                            }
                            break;
                        case 'empty': icon = ' '; break;
                    }
                    cellEl.html('<span class="cell-icon">' + icon + '</span>');
                }
                boardEl.append(cellEl);
            });
        }

        function renderScores(scores) {
            var html = '<h3>Scores</h3><ul>';
            scores.forEach(function(scoreData) {
                var isMyGroup = scoreData.group_id == myGroupId ? ' (Your Group)' : '';
                html += '<li>' + scoreData.group_name + isMyGroup + ': ' + scoreData.score + '</li>';
            });
            html += '</ul>';
            scoresEl.html(html);
        }

        function handleCellClick(row, col) {
            var data = {
                action: 'get_question_details',
                nonce: nonce,
                game_id: gameId,
                row: row,
                col: col
            };
            $.get(ajaxUrl, data, function(response) {
                if (response.success) {
                    if (response.data.type === 'question') {
                        showQuestionModal(row, col, response.data.details);
                    } else {
                        revealCell(row, col);
                    }
                } else {
                    getGameState();
                }
            });
        }

        function showQuestionModal(row, col, details) {
            var modalEl = $('#quiz-sweeper-modal');
            var choicesHtml = '';
            details.choices.forEach(function(choice, index) {
                if (choice) {
                    choicesHtml += `<label style="display: block; margin: 5px 0;"><input type="radio" name="answer" value="${index}" ${index === 0 ? 'checked' : ''}> ${choice}</label>`;
                }
            });

            var modalContent = `
                <div id="quiz-sweeper-modal-content">
                    <h3>${details.title}</h3>
                    <div id="question-answer-form">
                        ${choicesHtml}
                        <p style="margin-top: 15px;">
                            <button type="button" id="submit-answer-button" class="button button-primary">Submit Answer</button>
                        </p>
                    </div>
                </div>
            `;
            modalEl.html(modalContent).show();

            $('#submit-answer-button').on('click', function(e) {
                e.preventDefault();
                var answerIndex = $('#question-answer-form').find('input[name="answer"]:checked').val();
                revealCell(row, col, answerIndex);
                modalEl.hide().empty();
            });
        }

        function revealCell(row, col, answerIndex) {
            var data = {
                action: 'reveal_cell',
                nonce: nonce,
                game_id: gameId,
                row: row,
                col: col,
                answer_index: answerIndex
            };
            $.post(ajaxUrl, data, function(response) {
                if (response.success) {
                    var cellEl = boardEl.find('.cell[data-row="' + row + '"][data-col="' + col + '"]');
                    cellEl.off('click').addClass('revealed');
                    var icon = '';
                    switch(response.data.cell_type) {
                        case 'bomb': icon = '💣'; break;
                        case 'knife': icon = '🔪'; break;
                        case 'question':
                            if (response.data.was_correct) {
                                icon = '✅';
                                cellEl.addClass('correct');
                            } else {
                                icon = '❌';
                                cellEl.addClass('incorrect');
                            }
                            break;
                        case 'empty': icon = ' '; break;
                    }
                    cellEl.html('<span class="cell-icon">' + icon + '</span>');
                    getGameState();
                }
            });
        }

        function showGameOver(scores) {
            var finalScoresHtml = '<h2>Game Over!</h2><h3>Final Scores:</h3><ul>';
            scores.forEach(function(scoreData) {
                finalScoresHtml += '<li>' + scoreData.group_name + ': ' + scoreData.score + '</li>';
            });
            finalScoresHtml += '</ul>';
            finalScoresHtml += '<a href="/" class="button">Exit to Homepage</a>';

            boardEl.off('click');
            boardEl.after(finalScoresHtml);
        }

        // Initial fetch
        getGameState();

        // Set up polling
        gameStateInterval = setInterval(getGameState, 5000);
    });

})( jQuery );
