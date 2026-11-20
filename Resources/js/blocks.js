import '../css/popups.css';
import { bootPopups } from './runtime.js';

export default function registerPopups() {
    bootPopups(window, document);
}
