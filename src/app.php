<?php

$app = require_once __DIR__.'/bootstrap.php';

/**
 * Homepage, lists recent projects
 */
$app->get('/', function() use ($app) {
    $sql = <<<____SQL
        SELECT
            p.*,
            COUNT(c.id) AS comments,
            (SELECT COUNT(v.id)
                FROM project_vote AS v
                WHERE project_id = p.id
            ) AS votes,
            (SELECT COUNT(mv.id)
                FROM project_vote AS mv
                WHERE project_id = p.id
                   AND mv.username = ?
                LIMIT 1
            ) AS has_voted
        FROM project AS p
        LEFT JOIN comment AS c ON c.project_id = p.id
        GROUP BY p.id ORDER BY votes DESC
____SQL;

    $projects = $app['db']->fetchAll($sql, array($app['session']->get('username')));
    //$projects = $app['db']->fetchAll('SELECT p.*, COUNT(c.id) as comments FROM project p LEFT JOIN comment c ON c.project_id = p.id GROUP BY p.id ORDER BY id DESC');

    return $app['twig']->render('homepage.html.twig', array(
        'projects' => $projects,
    ));
})->bind('homepage');

$app->get('logout', function() use ($app) {
    $app['session']->remove('username');

    return $app->redirect($app['url_generator']->generate('homepage'));
})->bind('logout');

/**
 * Adds a comment to a project
 */
 $app->post('/project/{id}/comment', function($id) use ($app) {
    $form = $app['form.factory']->create(new Form\CommentType(), new Entity\Comment());
    $form->bindRequest($app['request']);

    if ($form->isValid()) {
        $comment = (array) $form->getData();

        unset($comment['id']);

        $comment['project_id']   = $id;
        $comment['username']     = $app['session']->get('username');
        $comment['content_html'] = $app['markdown']($comment['content']);

        $app['db']->insert('comment', $comment);

        return $app->redirect($app['url_generator']->generate('project_show', array('id' => $id)));
    }

    $project  = $app['db']->fetchAssoc('SELECT * FROM project WHERE id = ?', array($id));
    $comments = $app['db']->fetchAll('SELECT * FROM comment WHERE project_id = ?', array($id));

    return $app['twig']->render('Project/show.html.twig', array(
        'form'     => $form->createView(),
        'project'  => $project,
        'comments' => $comments,
    ));
 })->bind('project_comment');

/**
 * Deletes a project
 */
$app->post('/project/{id}/delete', function($id) use ($app) {
   $app['db']->delete('project', array('id' => $id));
   return $app->redirect($app['url_generator']->generate('homepage'));
})->bind('project_delete');

/**
 * Shows the edit form for a project
 */
$app->get('/project/{id}/edit', function($id) use ($app) {
    $project = $app['hydrate'](new Entity\Project(), $app['db']->fetchAssoc('SELECT * FROM project WHERE id = ?', array($id)));
    $form    = $app['form.factory']->create(new Form\ProjectType(), $project);

    return $app['twig']->render('Project/edit.html.twig', array(
        'form'    => $form->createView(),
        'project' => $project
    ));

})->bind('project_edit');

/**
 * Actually updates a project
 */
$app->post('/project/{id}', function($id) use ($app) {
    $project = $app['hydrate'](new Entity\Project(), $app['db']->fetchAssoc('SELECT * FROM project WHERE id = ?', array($id)));
    $form    = $app['form.factory']->create(new Form\ProjectType(), $project);

    $form->bindRequest($app['request']);

    if ($form->isValid()) {
        $project = (array) $form->getData();

        $project['id'] = $id;
        $project['description_html'] = $app['markdown']($project['description']);

        $app['db']->update('project', $project, array('id' => $id));

        return $app->redirect($app['url_generator']->generate('project_show', array('id' => $id)));
    }

    return $app['twig']->render('Project/edit.twig.html', array(
        'form'    => $form->createView(),
        'project' => $project,
    ));
})->bind('project_update');

/**
 * Project creation form
 */
$app->get('/project/new', function() use ($app) {
    $form = $app['form.factory']->create(new Form\ProjectType(), new Entity\Project());

    return $app['twig']->render('Project/new.html.twig', array(
        'form' => $form->createView(),
    ));
})->bind('project_new');

/**
 * Project show
 */
$app->get('/project/{id}', function($id) use ($app) {
    $project  = $app['db']->fetchAssoc('SELECT * FROM project WHERE id = ?', array($id));
    $comments = $app['db']->fetchAll('SELECT * FROM comment WHERE project_id = ?', array($id));
    $form     = $app['form.factory']->create(new Form\CommentType(), new Entity\Comment());

    return $app['twig']->render('Project/show.html.twig', array(
        'form'     => $form->createView(),
        'project'  => $project,
        'comments' => $comments,
    ));
})->bind('project_show');

/**
 * Project creation
 */
$app->post('/project', function() use ($app) {
    $form = $app['form.factory']->create(new Form\ProjectType(), new Entity\Project());

    $form->bindRequest($app['request']);

    if ($form->isValid()) {

        $project = (array) $form->getData();

        unset($project['id']);

        $project['username']         = $app['session']->get('username');
        $project['description_html'] = $app['markdown']($project['description']);

        $app['db']->insert('project', $project);

        return $app->redirect('/');
    }

    return $app['twig']->render('Project/new.html.twig', array(
        'form' => $form->createView(),
    ));

})->bind('project_create');

/**
 * Deletes a comment
 */
$app->post('/comment/{id}/delete', function($id) use ($app) {
    $comment = $app['db']->fetchAssoc('SELECT project_id FROM comment WHERE id = ?', array($id));
    $app['db']->delete('comment', array('id' => $id));
    return $app->redirect($app['url_generator']->generate('project_show', array('id' => $comment['project_id'])));
})->bind('comment_delete');

/**
 * Vote for project
 */
$app->get('/project/{id}/vote', function($id) use ($app) {
    $username = $app['session']->get('username');

    $sql = <<<____SQL
        SELECT id
        FROM project_vote
        WHERE username = ?
            AND project_id = ?
        LIMIT 1
____SQL;

    $exists = $app['db']->fetchColumn($sql, array($username, $id));
    if (!$exists) {
        $vote = array();
        $vote['username'] = $username;
        $vote['project_id'] = $id;
        $app['db']->insert('project_vote', $vote);
    }

    return $app->redirect('/');
})->bind('project_vote');

return $app;
