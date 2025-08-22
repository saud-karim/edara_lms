<?php
require_once 'config/config.php';
require_once 'php_action/auth.php';

// Only Super Admin can access this page
if (!isLoggedIn() || getUserRole() !== 'super_admin') {
    header("Location: dashboard.php");
    exit;
}

$pageTitle = 'إدارة الفرق الإدارية';
$currentPage = 'team_management';
include 'includes/header.php';
?>

<style>
/* ===== MODERN ENHANCED TEAM MANAGEMENT STYLES ===== */
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --secondary-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --accent-gradient: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
    --glass-bg: rgba(255, 255, 255, 0.1);
    --glass-border: rgba(255, 255, 255, 0.2);
    --shadow-light: 0 8px 32px rgba(31, 38, 135, 0.15);
    --shadow-medium: 0 12px 40px rgba(31, 38, 135, 0.25);
    --shadow-heavy: 0 20px 60px rgba(31, 38, 135, 0.35);
    --border-radius: 20px;
    --transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
}

body {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    min-height: 100vh;
    font-family: 'Cairo', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.content-wrapper {
    background: transparent;
}

/* ===== ENHANCED MAIN PANEL ===== */
.panel {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-medium);
    overflow: hidden;
}

.panel-heading {
    background: var(--primary-gradient) !important;
    color: white !important;
    padding: 25px 30px;
    border: none !important;
    position: relative;
    overflow: hidden;
}

.panel-heading::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.1) 50%, transparent 70%);
    transform: translateX(-100%);
    transition: transform 0.6s;
}

.panel:hover .panel-heading::after {
    transform: translateX(100%);
}

.panel-title {
    color: white !important;
    font-size: 24px;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 15px;
}

.panel-title i {
    font-size: 28px;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
}

.panel-body {
    padding: 40px;
    background: transparent;
}

/* ===== ENHANCED STATS CARDS ===== */
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 25px;
    margin-bottom: 40px;
}

.stat-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    padding: 30px 25px;
    border-radius: 18px;
    text-align: center;
    box-shadow: var(--shadow-light);
    border: 1px solid rgba(255, 255, 255, 0.3);
    transition: var(--transition);
    position: relative;
    overflow: hidden;
    cursor: pointer;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    transition: height 0.3s ease;
}

.stat-card:nth-child(1)::before { background: var(--primary-gradient); }
.stat-card:nth-child(2)::before { background: var(--secondary-gradient); }
.stat-card:nth-child(3)::before { background: var(--success-gradient); }
.stat-card:nth-child(4)::before { background: var(--warning-gradient); }

.stat-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: var(--shadow-heavy);
}

.stat-card:hover::before {
    height: 100%;
    opacity: 0.1;
}

.stat-number {
    font-size: 3.2em;
    font-weight: 800;
    margin-bottom: 12px;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.stat-card:nth-child(2) .stat-number { background: var(--secondary-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.stat-card:nth-child(3) .stat-number { background: var(--success-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.stat-card:nth-child(4) .stat-number { background: var(--warning-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

.stat-label {
    color: #555;
    font-size: 16px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: color 0.3s ease;
}

.stat-card:hover .stat-label {
    color: #333;
}

/* ===== ENHANCED ACTION BUTTONS ===== */
.btn-group {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 30px;
}

.btn-group .btn {
    padding: 14px 28px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 15px;
    transition: var(--transition);
    border: none;
    box-shadow: var(--shadow-light);
    position: relative;
    overflow: hidden;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn-group .btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    transition: left 0.5s;
}

.btn-group .btn:hover::before {
    left: 100%;
}

.btn-primary {
    background: var(--primary-gradient);
    color: white;
}

.btn-info {
    background: var(--secondary-gradient);
    color: white;
}

.btn-success {
    background: var(--success-gradient);
    color: white;
}

.btn-warning {
    background: var(--warning-gradient);
    color: white;
}

.btn-group .btn:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-medium);
}

.btn-group .btn i {
    margin-left: 8px;
    font-size: 16px;
    filter: drop-shadow(0 1px 2px rgba(0,0,0,0.2));
}

/* ===== ENHANCED TEAM CARDS ===== */
.team-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-medium);
    padding: 30px;
    margin-bottom: 30px;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.team-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--primary-gradient);
}

.team-card:hover {
    transform: translateY(-5px) scale(1.01);
    box-shadow: var(--shadow-heavy);
}

.team-header {
    background: var(--primary-gradient);
    color: white;
    padding: 25px 30px;
    border-radius: var(--border-radius) var(--border-radius) 0 0;
    margin: -30px -30px 30px -30px;
    position: relative;
    overflow: hidden;
}

.team-header h4 {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
}

.team-header i {
    font-size: 24px;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
}

/* ===== HEAD ADMIN CARDS ===== */
.head-admin-card {
    background: var(--secondary-gradient);
    color: white;
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: var(--shadow-medium);
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.head-admin-card::before {
    content: '👑';
    position: absolute;
    top: 15px;
    right: 20px;
    font-size: 28px;
    opacity: 0.4;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
}

.head-admin-card:hover {
    transform: translateX(5px) scale(1.02);
    box-shadow: var(--shadow-heavy);
}

.head-admin-info {
    margin-bottom: 15px;
}

.head-admin-info h5 {
    margin: 0 0 8px 0;
    font-size: 18px;
    font-weight: 700;
}

.head-admin-info p {
    margin: 0;
    opacity: 0.9;
    font-size: 14px;
}

/* ===== SUB ADMIN CARDS ===== */
.sub-admin-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(15px);
    border: 2px solid transparent;
    border-radius: 14px;
    padding: 20px;
    margin: 15px 0;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
    cursor: pointer;
}

.sub-admin-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: var(--success-gradient);
    opacity: 0;
    transition: opacity 0.3s ease;
    z-index: -1;
}

.sub-admin-card:hover {
    transform: translateY(-3px) scale(1.02);
    box-shadow: var(--shadow-medium);
    color: white;
    border-color: transparent;
}

.sub-admin-card:hover::before {
    opacity: 1;
}

.sub-admin-info h6 {
    margin: 0 0 8px 0;
    font-size: 16px;
    font-weight: 600;
    transition: color 0.3s ease;
}

.sub-admin-info p {
    margin: 0;
    font-size: 13px;
    opacity: 0.8;
    transition: color 0.3s ease;
}

/* ===== ACTION BUTTONS FOR USERS ===== */
.action-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 15px;
}

.btn-move, .btn-promote {
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 13px;
    transition: var(--transition);
    border: none;
    cursor: pointer;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.btn-move {
    background: var(--accent-gradient);
    color: #8b4513;
    box-shadow: 0 4px 15px rgba(252, 182, 159, 0.3);
}

.btn-move:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(252, 182, 159, 0.5);
    color: #5d2f08;
}

.btn-promote {
    background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
    color: #2c3e50;
    box-shadow: 0 4px 15px rgba(168, 237, 234, 0.3);
}

.btn-promote:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(168, 237, 234, 0.5);
    color: #1a252f;
}

/* ===== TREE-STYLE HIERARCHY VIEW ===== */
.hierarchy-view {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: var(--border-radius);
    padding: 40px;
    margin-bottom: 30px;
    box-shadow: var(--shadow-medium);
    border: 1px solid rgba(255, 255, 255, 0.3);
    overflow-x: auto;
}

.org-chart {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 800px;
    font-family: 'Cairo', sans-serif;
}

/* Root Node - Super Admin */
.super-admin-node {
    background: var(--primary-gradient);
    color: white;
    padding: 20px 40px;
    border-radius: 15px;
    margin-bottom: 60px;
    font-weight: 700;
    font-size: 18px;
    box-shadow: var(--shadow-heavy);
    transition: all 0.3s ease;
    position: relative;
    border: 3px solid rgba(255,255,255,0.3);
}

.super-admin-node::before {
    content: '👑';
    position: absolute;
    top: -15px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 24px;
    background: white;
    padding: 5px;
    border-radius: 50%;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.super-admin-node::after {
    content: '';
    position: absolute;
    bottom: -30px;
    left: 50%;
    transform: translateX(-50%);
    width: 3px;
    height: 30px;
    background: linear-gradient(to bottom, #667eea, transparent);
}

.super-admin-node:hover {
    transform: scale(1.05) translateY(-5px);
    box-shadow: var(--shadow-heavy);
}

/* Tree Structure Container */
.teams-container {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 50px;
    position: relative;
    padding-top: 20px;
}

/* Horizontal Connection Line */
.teams-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 15%;
    right: 15%;
    height: 3px;
    background: linear-gradient(to right, transparent, #667eea 20%, #667eea 80%, transparent);
    border-radius: 2px;
    z-index: 1;
}

/* Team Branch */
.team-branch {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    min-width: 280px;
    max-width: 320px;
}

/* Vertical Connection to Branch */
.team-branch::before {
    content: '';
    position: absolute;
    top: -20px;
    left: 50%;
    transform: translateX(-50%);
    width: 3px;
    height: 20px;
    background: #667eea;
    border-radius: 2px;
    z-index: 2;
}

/* Head Admin Node */
.head-admin-node {
    background: var(--secondary-gradient);
    color: white;
    padding: 20px 25px;
    border-radius: 12px;
    margin-bottom: 30px;
    font-weight: 600;
    font-size: 15px;
    box-shadow: var(--shadow-medium);
    transition: all 0.3s ease;
    position: relative;
    border: 2px solid rgba(255,255,255,0.2);
    text-align: center;
    min-height: 85px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    width: 100%;
}

.head-admin-node::before {
    content: '⭐';
    position: absolute;
    top: -8px;
    right: -8px;
    font-size: 16px;
    background: white;
    padding: 3px;
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.head-admin-node:hover {
    transform: scale(1.03);
    box-shadow: var(--shadow-heavy);
}

/* Sub Admins Container */
.sub-admins-tree {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    width: 100%;
    gap: 15px;
}

/* Vertical Line from Head to Sub Admins */
.sub-admins-tree::before {
    content: '';
    position: absolute;
    top: -30px;
    left: 50%;
    transform: translateX(-50%);
    width: 3px;
    height: 30px;
    background: #11998e;
    border-radius: 2px;
    z-index: 1;
}

/* Individual Sub Admin Node */
.sub-admin-node {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border: 2px solid #11998e;
    border-radius: 10px;
    padding: 15px 20px;
    font-weight: 500;
    font-size: 14px;
    box-shadow: var(--shadow-light);
    transition: all 0.3s ease;
    position: relative;
    text-align: center;
    min-height: 65px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    color: #2c3e50;
    width: 90%;
}

.sub-admin-node::before {
    content: '';
    position: absolute;
    top: -17px;
    left: 50%;
    transform: translateX(-50%);
    width: 3px;
    height: 15px;
    background: #11998e;
    border-radius: 2px;
    z-index: 2;
}

.sub-admin-node::after {
    content: '👤';
    position: absolute;
    top: -8px;
    right: -8px;
    font-size: 12px;
    background: #11998e;
    color: white;
    padding: 3px;
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.sub-admin-node:hover {
    transform: translateY(-3px) scale(1.02);
    box-shadow: var(--shadow-medium);
    border-color: #0d7377;
    background: rgba(17, 153, 142, 0.1);
}

/* Empty Team Styling */
.empty-team-node {
    background: rgba(255, 255, 255, 0.7);
    border: 2px dashed #ddd;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    color: #999;
    font-style: italic;
    transition: all 0.3s ease;
    min-height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 90%;
}

.empty-team-node:hover {
    border-color: #bbb;
    background: rgba(255, 255, 255, 0.9);
}

/* Department Label */
.department-label {
    font-size: 12px;
    opacity: 0.8;
    margin-top: 5px;
    padding: 3px 8px;
    background: rgba(255,255,255,0.2);
    border-radius: 10px;
}

/* Statistics in nodes */
.node-stats {
    font-size: 11px;
    margin-top: 8px;
    opacity: 0.9;
    display: flex;
    justify-content: center;
    gap: 10px;
}

.node-stats span {
    padding: 2px 6px;
    background: rgba(255,255,255,0.2);
    border-radius: 8px;
    white-space: nowrap;
}

/* Animation for tree loading */
@keyframes treeGrow {
    from {
        opacity: 0;
        transform: scale(0.8) translateY(20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.team-branch {
    animation: treeGrow 0.6s ease forwards;
}

.team-branch:nth-child(2) { animation-delay: 0.1s; }
.team-branch:nth-child(3) { animation-delay: 0.2s; }
.team-branch:nth-child(4) { animation-delay: 0.3s; }
.team-branch:nth-child(5) { animation-delay: 0.4s; }

/* Responsive Tree Design */
@media (max-width: 768px) {
    .org-chart {
        min-width: auto;
    }
    
    .teams-container {
        flex-direction: column;
        gap: 30px;
        align-items: center;
    }
    
    .team-branch {
        min-width: auto;
        max-width: 100%;
        width: 100%;
    }
    
    .teams-container::before {
        display: none;
    }
    
    .team-branch::before {
        display: none;
    }
    
    .super-admin-node::after {
        display: none;
    }
}

/* ===== EMPTY STATE ===== */
.empty-team {
    text-align: center;
    padding: 60px 40px;
    color: #777;
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    border: 2px dashed #ddd;
    transition: var(--transition);
    margin: 20px 0;
}

.empty-team:hover {
    border-color: #bbb;
    background: rgba(255, 255, 255, 0.9);
    transform: scale(1.01);
}

.empty-team i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

/* ===== LOADING ANIMATION ===== */
@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

#loadingIndicator {
    animation: pulse 2s infinite;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 16px;
    padding: 50px 40px;
    box-shadow: var(--shadow-light);
    text-align: center;
}

#loadingIndicator i {
    animation: float 3s ease-in-out infinite;
}

/* ===== RESPONSIVE DESIGN ===== */
@media (max-width: 768px) {
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    
    .teams-container {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .btn-group {
        flex-direction: column;
        gap: 10px;
    }
    
    .btn-group .btn {
        width: 100%;
        margin-bottom: 0;
    }
    
    .team-card, .hierarchy-view {
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .panel-body {
        padding: 20px;
    }
    
    .stat-number {
        font-size: 2.5em;
    }
}

@media (max-width: 480px) {
    .stats-row {
        grid-template-columns: 1fr;
    }
    
    .stat-number {
        font-size: 2.2em;
    }
    
    .panel-title {
        font-size: 20px;
    }
    
    .super-admin-node {
        padding: 15px 30px;
        font-size: 16px;
    }
}

/* ===== BEAUTIFUL SCROLLBAR ===== */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: var(--primary-gradient);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--secondary-gradient);
}

/* ===== ENHANCED ANIMATIONS ===== */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.team-card, .stat-card {
    animation: fadeInUp 0.6s ease forwards;
}

.team-card:nth-child(even) {
    animation-delay: 0.1s;
}

.stat-card:nth-child(2) { animation-delay: 0.1s; }
.stat-card:nth-child(3) { animation-delay: 0.2s; }
.stat-card:nth-child(4) { animation-delay: 0.3s; }

/* ===== ENHANCED TREE-STYLE HIERARCHY ===== */
.hierarchy-view {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 40px;
    margin-bottom: 30px;
    box-shadow: 0 12px 40px rgba(31, 38, 135, 0.25);
    border: 1px solid rgba(255, 255, 255, 0.3);
    overflow-x: auto;
}

.org-chart {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 800px;
    font-family: 'Cairo', sans-serif;
}

/* Root Node - Super Admin with Crown */
.super-admin-node {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px 40px;
    border-radius: 15px;
    margin-bottom: 60px;
    font-weight: 700;
    font-size: 18px;
    box-shadow: 0 20px 60px rgba(31, 38, 135, 0.35);
    transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
    position: relative;
    border: 3px solid rgba(255,255,255,0.3);
}

.super-admin-node::before {
    content: '👑';
    position: absolute;
    top: -15px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 24px;
    background: white;
    padding: 5px;
    border-radius: 50%;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.super-admin-node::after {
    content: '';
    position: absolute;
    bottom: -30px;
    left: 50%;
    transform: translateX(-50%);
    width: 3px;
    height: 30px;
    background: linear-gradient(to bottom, #667eea, transparent);
}

.super-admin-node:hover {
    transform: scale(1.05) translateY(-5px);
    box-shadow: 0 25px 70px rgba(31, 38, 135, 0.4);
}

/* Tree Structure Container */
.teams-container {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 50px;
    position: relative;
    padding-top: 20px;
}

/* Horizontal Connection Line */
.teams-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 15%;
    right: 15%;
    height: 3px;
    background: linear-gradient(to right, transparent, #667eea 20%, #667eea 80%, transparent);
    border-radius: 2px;
    z-index: 1;
}

/* Team Branch */
.team-branch {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    min-width: 280px;
    max-width: 320px;
    animation: treeGrow 0.6s ease forwards;
}

/* Vertical Connection to Branch */
.team-branch::before {
    content: '';
    position: absolute;
    top: -20px;
    left: 50%;
    transform: translateX(-50%);
    width: 3px;
    height: 20px;
    background: #667eea;
    border-radius: 2px;
    z-index: 2;
}

/* Head Admin Node */
.head-admin-node {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    color: white;
    padding: 20px 25px;
    border-radius: 12px;
    margin-bottom: 30px;
    font-weight: 600;
    font-size: 15px;
    box-shadow: 0 12px 40px rgba(31, 38, 135, 0.25);
    transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
    position: relative;
    border: 2px solid rgba(255,255,255,0.2);
    text-align: center;
    min-height: 85px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    width: 100%;
}

.head-admin-node::before {
    content: '⭐';
    position: absolute;
    top: -8px;
    right: -8px;
    font-size: 16px;
    background: white;
    padding: 3px;
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.head-admin-node:hover {
    transform: scale(1.03);
    box-shadow: 0 20px 60px rgba(31, 38, 135, 0.35);
}

/* Sub Admins Container */
.sub-admins-tree {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    width: 100%;
    gap: 15px;
}

/* Vertical Line from Head to Sub Admins */
.sub-admins-tree::before {
    content: '';
    position: absolute;
    top: -30px;
    left: 50%;
    transform: translateX(-50%);
    width: 3px;
    height: 30px;
    background: #11998e;
    border-radius: 2px;
    z-index: 1;
}

/* Individual Sub Admin Node */
.sub-admin-node {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border: 2px solid #11998e;
    border-radius: 10px;
    padding: 15px 20px;
    font-weight: 500;
    font-size: 14px;
    box-shadow: 0 8px 32px rgba(31, 38, 135, 0.15);
    transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
    position: relative;
    text-align: center;
    min-height: 65px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    color: #2c3e50;
    width: 90%;
}

.sub-admin-node::before {
    content: '';
    position: absolute;
    top: -17px;
    left: 50%;
    transform: translateX(-50%);
    width: 3px;
    height: 15px;
    background: #11998e;
    border-radius: 2px;
    z-index: 2;
}

.sub-admin-node::after {
    content: '👤';
    position: absolute;
    top: -8px;
    right: -8px;
    font-size: 12px;
    background: #11998e;
    color: white;
    padding: 3px;
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.sub-admin-node:hover {
    transform: translateY(-3px) scale(1.02);
    box-shadow: 0 12px 40px rgba(31, 38, 135, 0.25);
    border-color: #0d7377;
    background: rgba(17, 153, 142, 0.1);
}

/* Empty Team Styling */
.empty-team-node {
    background: rgba(255, 255, 255, 0.7);
    border: 2px dashed #ddd;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    color: #999;
    font-style: italic;
    transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
    min-height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 90%;
}

.empty-team-node:hover {
    border-color: #bbb;
    background: rgba(255, 255, 255, 0.9);
}

/* Styling for names and labels */
.admin-name {
    font-weight: 600;
    margin-bottom: 5px;
}

.department-label {
    font-size: 12px;
    opacity: 0.8;
    margin-top: 5px;
    padding: 3px 8px;
    background: rgba(255,255,255,0.2);
    border-radius: 10px;
}

.node-stats {
    font-size: 11px;
    margin-top: 8px;
    opacity: 0.9;
    display: flex;
    justify-content: center;
    gap: 10px;
}

.node-stats span {
    padding: 2px 6px;
    background: rgba(255,255,255,0.2);
    border-radius: 8px;
    white-space: nowrap;
}

/* Tree Growing Animation */
@keyframes treeGrow {
    from {
        opacity: 0;
        transform: scale(0.8) translateY(20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.team-branch:nth-child(2) { animation-delay: 0.1s; }
.team-branch:nth-child(3) { animation-delay: 0.2s; }
.team-branch:nth-child(4) { animation-delay: 0.3s; }
.team-branch:nth-child(5) { animation-delay: 0.4s; }

/* Responsive Tree Design */
@media (max-width: 768px) {
    .org-chart {
        min-width: auto;
    }
    
    .teams-container {
        flex-direction: column;
        gap: 30px;
        align-items: center;
    }
    
    .team-branch {
        min-width: auto;
        max-width: 100%;
        width: 100%;
    }
    
    .teams-container::before,
    .team-branch::before,
    .super-admin-node::after {
        display: none;
    }
}
</style>

<div class="container content-wrapper">
    <?php displayMessage(); ?>
    
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <i class="glyphicon glyphicon-users"></i>
                        إدارة الفرق الإدارية المتقدمة
                    </h4>
                </div>
                
                <div class="panel-body">
                    <!-- Statistics Overview -->
                    <div class="stats-row">
                        <div class="stat-card">
                            <div class="stat-number" id="totalUsersCount">0</div>
                            <div class="stat-label">إجمالي المستخدمين</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" id="headAdminsCount">0</div>
                            <div class="stat-label">مديرين رئيسيين</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" id="subAdminsCount">0</div>
                            <div class="stat-label">مديرين فرعيين</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" id="regularUsersCount">0</div>
                            <div class="stat-label">مستخدمين عاديين</div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="row" style="margin-bottom: 25px;">
                        <div class="col-md-12">
                            <div class="btn-group" role="group">
                                <button id="refreshTeams" class="btn btn-primary">
                                    <i class="glyphicon glyphicon-refresh"></i> تحديث البيانات
                                </button>
                                <button id="showHierarchy" class="btn btn-info">
                                    <i class="glyphicon glyphicon-tree-deciduous"></i> عرض الهيكل التنظيمي
                                </button>
                                <a href="add_user.php" class="btn btn-success">
                                    <i class="glyphicon glyphicon-plus"></i> إضافة مستخدم جديد
                                </a>
                                <button id="exportTeams" class="btn btn-warning">
                                    <i class="glyphicon glyphicon-export"></i> تصدير البيانات
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Hierarchy View (Hidden by default) -->
                    <div id="hierarchyView" class="hierarchy-view" style="display: none;">
                        <h3 style="text-align: center; margin-bottom: 30px; color: #333; font-weight: 700;">
                            <i class="glyphicon glyphicon-tree-deciduous"></i> الهيكل التنظيمي للنظام
                        </h3>
                        <div id="hierarchyContent">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Teams List -->
                    <div id="teamsContainer">
                        <div id="loadingIndicator" class="text-center" style="padding: 40px;">
                            <i class="fa fa-spinner fa-spin fa-3x text-primary"></i>
                            <p style="margin-top: 15px; font-weight: 600; color: #667eea;">جاري تحميل بيانات الفرق...</p>
                        </div>
                    </div>
                    
                    <!-- Independent Head Admins (No Team) -->
                    <div id="independentAdmins" style="display: none;">
                        <h3 style="margin-top: 30px; color: #333; font-weight: 700;">
                            <i class="glyphicon glyphicon-user"></i> مديرين رئيسيين بدون فريق
                        </h3>
                        <div id="independentAdminsList"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Move User Modal -->
<div class="modal fade" id="moveUserModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 15px; border: none; box-shadow: var(--shadow-heavy);">
            <div class="modal-header" style="background: var(--primary-gradient); color: white; border-radius: 15px 15px 0 0;">
                <button type="button" class="close" data-dismiss="modal" style="color: white; opacity: 0.8;">
                    <span>&times;</span>
                </button>
                <h4 class="modal-title">نقل المستخدم</h4>
            </div>
            <div class="modal-body" style="padding: 30px;">
                <form id="moveUserForm">
                    <input type="hidden" id="moveUserId" name="user_id">
                    <div class="form-group">
                        <label for="newParentId" style="font-weight: 600; color: #333;">المدير الجديد:</label>
                        <select id="newParentId" name="new_parent_id" class="form-control" style="border-radius: 8px; border: 2px solid #e3f2fd;">
                            <option value="">-- جعله مدير رئيسي --</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="border: none; padding: 20px 30px;">
                <button type="button" class="btn btn-default" data-dismiss="modal" style="border-radius: 8px;">إلغاء</button>
                <button type="button" id="confirmMove" class="btn btn-primary" style="background: var(--primary-gradient); border: none; border-radius: 8px;">تأكيد النقل</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    let teamsData = [];
    let hierarchyData = [];
    
    // Load initial data
    loadTeamsData();
    
    // Event handlers
    $('#refreshTeams').click(loadTeamsData);
    $('#showHierarchy').click(toggleHierarchyView);
    $('#exportTeams').click(exportTeamsData);
    
    function loadTeamsData() {
        $('#loadingIndicator').show();
        $('#teamsContainer .team-card').remove();
        
        $.ajax({
            url: 'php_action/get_teams_data.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                console.log('API Response:', response); // Debug log
                if (response.success) {
                    teamsData = response.teams;
                    updateStatistics(response.stats);
                    displayTeams(response.teams);
                } else {
                    showAlert('خطأ في تحميل البيانات: ' + (response.message || 'غير معروف'), 'danger');
                }
            },
            error: function() {
                showAlert('فشل في الاتصال بالخادم', 'danger');
            },
            complete: function() {
                $('#loadingIndicator').hide();
            }
        });
    }
    
    function updateStatistics(stats) {
        $('#totalUsersCount').text(stats.total_users || 0);
        $('#headAdminsCount').text(stats.head_admins || 0);
        $('#subAdminsCount').text(stats.sub_admins || 0);
        $('#regularUsersCount').text(stats.regular_users || 0);
    }
    
    function displayTeams(teams) {
        console.log('Displaying teams:', teams); // Debug log
        let html = '';
        let independentAdminsHtml = '';
        
        teams.forEach(team => {
            if (team.sub_admins && team.sub_admins.length > 0) {
                html += generateTeamCard(team);
            } else {
                independentAdminsHtml += generateTeamCard(team); // Use same function for consistency
            }
        });
        
        $('#teamsContainer').append(html);
        
        if (independentAdminsHtml) {
            $('#independentAdminsList').html(independentAdminsHtml);
            $('#independentAdmins').show();
        } else {
            $('#independentAdmins').hide();
        }
    }
    
    function generateTeamCard(team) {
        let subAdminsHtml = '';
        team.sub_admins.forEach(subAdmin => {
            subAdminsHtml += `
                <div class="sub-admin-card">
                    <div class="sub-admin-info">
                        <h6>${subAdmin.full_name}</h6>
                        <p><i class="glyphicon glyphicon-user"></i> ${subAdmin.username} | 
                           <i class="glyphicon glyphicon-home"></i> ${subAdmin.department_name || 'غير محدد'}</p>
                    </div>
                    <div class="action-buttons">
                        <button class="btn-move" onclick="openMoveModal(${subAdmin.user_id}, '${subAdmin.full_name}')">
                            <i class="glyphicon glyphicon-transfer"></i> نقل
                        </button>
                        <button class="btn-promote" onclick="promoteToHead(${subAdmin.user_id}, '${subAdmin.full_name}')">
                            <i class="glyphicon glyphicon-star"></i> ترقية
                        </button>
                    </div>
                </div>`;
        });
        
        return `
            <div class="team-card">
                <div class="team-header">
                    <h4><i class="glyphicon glyphicon-users"></i> فريق ${team.head_admin.full_name}</h4>
                </div>
                <div class="head-admin-card">
                    <div class="head-admin-info">
                        <h5>${team.head_admin.full_name}</h5>
                        <p><i class="glyphicon glyphicon-user"></i> ${team.head_admin.username} | 
                           <i class="glyphicon glyphicon-home"></i> ${team.head_admin.department_name || 'غير محدد'}</p>
                        <p><i class="glyphicon glyphicon-file"></i> ${team.stats.total_personal_licenses || 0} رخصة شخصية | 
                           <i class="glyphicon glyphicon-road"></i> ${team.stats.total_vehicle_licenses || 0} رخصة مركبة</p>
                    </div>
                </div>
                <div class="sub-admins-section">
                    <h5 style="margin-bottom: 15px; color: #333; font-weight: 600;">
                        <i class="glyphicon glyphicon-users"></i> المديرين الفرعيين (${team.sub_admins.length})
                    </h5>
                    ${subAdminsHtml}
                </div>
            </div>`;
    }
    
    function generateIndependentAdminCard(admin) {
        return `
            <div class="team-card">
                <div class="head-admin-card">
                    <div class="head-admin-info">
                        <h5>${admin.full_name}</h5>
                        <p><i class="glyphicon glyphicon-user"></i> ${admin.username} | 
                           <i class="glyphicon glyphicon-home"></i> ${admin.department_name || 'غير محدد'}</p>
                        <p><i class="glyphicon glyphicon-file"></i> ${admin.personal_licenses_count || 0} رخصة شخصية | 
                           <i class="glyphicon glyphicon-road"></i> ${admin.vehicle_licenses_count || 0} رخصة مركبة</p>
                    </div>
                </div>
                <div class="empty-team">
                    <i class="glyphicon glyphicon-user"></i>
                    <p>مدير رئيسي بدون فريق</p>
                </div>
            </div>`;
    }
    
    function toggleHierarchyView() {
        if ($('#hierarchyView').is(':visible')) {
            $('#hierarchyView').slideUp();
            $('#showHierarchy').html('<i class="glyphicon glyphicon-tree-deciduous"></i> عرض الهيكل التنظيمي');
        } else {
            loadHierarchyData();
        }
    }
    
    function loadHierarchyData() {
        $.ajax({
            url: 'php_action/get_hierarchy.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                console.log("Hierarchy API Response:", response); // Debug log
                if (response.success) {
                    displayHierarchy(response.hierarchy);
                    $('#hierarchyView').slideDown();
                    $('#showHierarchy').html('<i class="glyphicon glyphicon-eye-close"></i> إخفاء الهيكل التنظيمي');
                } else {
                    console.error('Hierarchy error:', response.message);
                    showAlert('خطأ في تحميل الهيكل التنظيمي: ' + (response.message || 'غير معروف'), 'danger');
                }
            },
            error: function(xhr, status, error) {
                console.error('Hierarchy AJAX Error:', error);
                showAlert('فشل في الاتصال بالخادم للهيكل التنظيمي', 'danger');
            }
        });
    }
    
    function displayHierarchy(data) {
        console.log('Creating tree hierarchy:', data);
        let html = `
            <div class="org-chart">
                <div class="super-admin-node">
                    <i class="glyphicon glyphicon-star"></i> المديرين العامين (${data.super_admin_count})
                </div>
                <div class="teams-container">`;
                
        data.teams.forEach((team, index) => {
            let subAdminsHtml = '';
            
            if (team.sub_admins && team.sub_admins.length > 0) {
                subAdminsHtml = '<div class="sub-admins-tree">';
                team.sub_admins.forEach(sub => {
                    subAdminsHtml += `
                        <div class="sub-admin-node">
                            <div class="admin-name">${sub.name}</div>
                            <div class="department-label">
                                <i class="glyphicon glyphicon-home"></i> قسم فرعي
                            </div>
                        </div>`;
                });
                subAdminsHtml += '</div>';
            } else {
                subAdminsHtml = `
                    <div class="sub-admins-tree">
                        <div class="empty-team-node">
                            <i class="glyphicon glyphicon-user"></i> بدون فريق
                        </div>
                    </div>`;
            }
            
            html += `
                <div class="team-branch">
                    <div class="head-admin-node">
                        <div class="admin-name">${team.head_admin_name}</div>
                        <div class="department-label">
                            <i class="glyphicon glyphicon-home"></i> ${team.head_admin_department || 'غير محدد'}
                        </div>
                        <div class="node-stats">
                            <span><i class="glyphicon glyphicon-users"></i> ${team.sub_admins ? team.sub_admins.length : 0}</span>
                        </div>
                    </div>
                    ${subAdminsHtml}
                </div>`;
        });
        
        html += `</div></div>`;
        $('#hierarchyContent').html(html);
    }
    
    function exportTeamsData() {
        window.open('php_action/export_teams.php', '_blank');
    }
    
    function openMoveModal(userId, userName) {
        $('#moveUserId').val(userId);
        $('#moveUserModal .modal-title').text(`نقل المستخدم: ${userName}`);
        
        // Load available head admins
        $.ajax({
            url: 'php_action/get_head_admins_all.php',
            method: 'POST',
            data: { exclude_user_id: userId },
            dataType: 'json',
            success: function(response) {
                let options = '<option value="">-- جعله مدير رئيسي --</option>';
                if (response.success && response.data) {
                    response.data.forEach(admin => {
                        options += `<option value="${admin.user_id}">${admin.full_name} (${admin.username})</option>`;
                    });
                }
                $('#newParentId').html(options);
                $('#moveUserModal').modal('show');
            }
        });
    }
    
    function promoteToHead(userId, userName) {
        if (confirm(`هل تريد ترقية ${userName} ليصبح مدير رئيسي؟`)) {
            moveUser(userId, null);
        }
    }
    
    $('#confirmMove').click(function() {
        const userId = $('#moveUserId').val();
        const newParentId = $('#newParentId').val() || null;
        moveUser(userId, newParentId);
    });
    
    function moveUser(userId, newParentId) {
        $.ajax({
            url: 'php_action/move_user.php',
            method: 'POST',
            data: {
                user_id: userId,
                new_parent_id: newParentId
            },
            dataType: 'json',
            success: function(response) {
                console.log("API Response:", response); // Debug log
                if (response.success) {
                    showAlert('تم نقل المستخدم بنجاح', 'success');
                    $('#moveUserModal').modal('hide');
                    loadTeamsData();
                } else {
                    showAlert(response.message || 'فشل في نقل المستخدم', 'danger');
                }
            },
            error: function() {
                showAlert('خطأ في الاتصال بالخادم', 'danger');
            }
        });
    }
    
    function showAlert(message, type) {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const alert = `<div class="alert ${alertClass} alert-dismissible" style="border-radius: 10px; border: none;">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            ${message}
        </div>`;
        $('.content-wrapper').prepend(alert);
        
        setTimeout(() => {
            $('.alert').fadeOut();
        }, 5000);
    }
});
</script>

<?php include 'includes/footer.php'; ?> 